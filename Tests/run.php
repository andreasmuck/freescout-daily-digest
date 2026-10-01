<?php
// Run only against a disposable FreeScout 1.8.241 checkout with bundled vendor/.
// Usage: php Modules/DailyDigest/Tests/run.php /absolute/path/to/test-checkout
$root = realpath($argv[1] ?? '');
if (!$root || !file_exists($root.'/vendor/autoload.php') || file_exists($root.'/.env') || file_exists($root.'/bootstrap/cache/config.php') || file_exists($root.'/storage/.installed')) {
    fwrite(STDERR, "Use a disposable checkout without .env, installed marker, or cached configuration.\n"); exit(1);
}
$database = tempnam(sys_get_temp_dir(), 'daily-digest-test-');
register_shutdown_function(function () use ($database) { if (file_exists($database)) { unlink($database); } });
$pdo = new PDO('sqlite:'.$database);
$pdo->exec('CREATE TABLE options (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(191) UNIQUE, value TEXT)');
$pdo->exec('CREATE TABLE modules (id INTEGER PRIMARY KEY AUTOINCREMENT, alias VARCHAR(191), active INTEGER, activated INTEGER, license VARCHAR(32))');
$pdo->exec("INSERT INTO modules (alias, active, activated) VALUES ('dailydigest', 1, 1)");
foreach (['APP_ENV'=>'testing','APP_DEBUG'=>'true','APP_KEY'=>'base64:'.base64_encode(str_repeat('t',32)),'DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'CACHE_DRIVER'=>'array','SESSION_DRIVER'=>'array','APP_URL'=>'https://example.test/helpdesk','APP_TIMEZONE'=>'UTC','MAIL_DRIVER'=>'array'] as $key=>$value) {
    putenv($key.'='.$value); $_ENV[$key]=$value; $_SERVER[$key]=$value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\HandleExceptions::class, function () { error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED); });
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

use App\User;
use App\Option;
use Carbon\Carbon;
use Modules\DailyDigest\Services\Settings;
use Modules\DailyDigest\Services\DigestBuilder;
use Modules\DailyDigest\Services\DigestMailer;
use Modules\DailyDigest\Services\DeliveryLedger;

$checks = 0;
function check($value, $label) { global $checks; if (!$value) { throw new RuntimeException('FAIL: '.$label); } $checks++; echo "PASS: $label\n"; }
function option($key, $value) { Option::set($key, $value); Option::$cache = []; }

try {
    check(config('database.default') === 'sqlite' && config('database.connections.sqlite.database') === $database, 'isolated SQLite database');
    check(isset(Artisan::all()['freescout:daily-digest']), 'module auto-discovery and command registration');
    $installedModule=Module::findByAlias('dailydigest');
    check($installedModule->get('latestVersionUrl')==='https://github.com/andreasmuck/freescout-daily-digest/releases/latest/download/version.txt', 'FreeScout loads the correct online version metadata key');
    check($installedModule->get('latestVersionZipUrl')==='https://github.com/andreasmuck/freescout-daily-digest/releases/latest/download/DailyDigest.zip', 'FreeScout loads the stable update ZIP URL');
    check(!$installedModule->get('latestVersionNumberUrl'), 'obsolete version metadata key is absent');
    check(Route::has('dailydigest.preview'), 'preview route registration');
    $route = Route::getRoutes()->getByName('dailydigest.preview');
    check($route->getAction('roles') === ['admin'] && in_array('auth', $route->middleware()), 'preview restricted to authenticated admins');
    check(isset(Eventy::filter('settings.sections', [])['daily-digest']), 'global settings hook');
    $schedule = Eventy::filter('schedule', new Illuminate\Console\Scheduling\Schedule());
    $digestEvents = array_values(array_filter($schedule->events(), function ($event) { return strpos($event->command ?? '', 'freescout:daily-digest') !== false; }));
    check(count($digestEvents) === 1 && $digestEvents[0]->expression === '*/5 * * * * *', 'scheduler hook');

    Schema::create('users', function($t) { $t->increments('id'); $t->string('first_name'); $t->string('last_name'); $t->string('email'); $t->integer('status'); $t->integer('type'); $t->integer('role'); $t->text('permissions')->nullable(); $t->text('meta')->nullable(); $t->string('locale')->default('en'); $t->string('timezone')->default('UTC'); $t->timestamps(); });
    Schema::create('mailboxes', function($t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->text('meta')->nullable(); $t->timestamps(); });
    Schema::create('mailbox_user', function($t) { $t->increments('id'); $t->integer('mailbox_id'); $t->integer('user_id'); $t->text('access')->nullable(); $t->integer('hide')->default(0); $t->integer('mute')->default(0); });
    Schema::create('conversations', function($t) { $t->increments('id'); $t->integer('number'); $t->integer('user_id')->nullable(); $t->integer('mailbox_id'); $t->integer('status'); $t->integer('state'); $t->string('subject'); $t->integer('created_by_user_id')->nullable(); $t->timestamps(); });
    Schema::create('threads', function($t) { $t->increments('id'); $t->integer('conversation_id'); $t->integer('state'); $t->integer('type')->default(1); $t->timestamps(); });
    foreach ([1=>[1,1,1],2=>[1,1,1],3=>[2,1,1],4=>[3,1,1],5=>[1,2,1],6=>[1,1,2]] as $id=>$flags) {
        DB::table('users')->insert(['id'=>$id,'first_name'=>'Agent '.$id,'last_name'=>'Test','email'=>'agent'.$id.'@example.test','status'=>$flags[0],'type'=>$flags[1],'role'=>$flags[2]]);
    }
    foreach ([1=>'Support',2=>'Billing',3=>'Private',4=>'Archive'] as $id=>$name) {
        DB::table('mailboxes')->insert(['id'=>$id,'name'=>$name,'email'=>'box'.$id.'@example.test','meta'=>$id===4 ? json_encode(['st'=>2]) : '{}']);
    }
    foreach ([1,2,4] as $mailbox) { DB::table('mailbox_user')->insert(['mailbox_id'=>$mailbox,'user_id'=>1]); }
    DB::table('mailbox_user')->insert(['mailbox_id'=>1,'user_id'=>2]);
    $now = Carbon::parse('2026-09-29 13:00:00', 'UTC'); Carbon::setTestNow($now);
    $old = '2026-09-19 10:00:00';
    $fixtures = [
        1=>[1,1,1,2,$old,'<script>alert("unsafe")</script> & quote'],
        2=>[1,2,1,2,$old,'Billing issue'],
        3=>[1,3,1,2,$old,'Private mailbox'],
        4=>[1,4,1,2,$old,'Archived mailbox'],
        5=>[1,1,3,2,$old,'Closed'],
        6=>[1,1,2,2,$old,'Pending'],
        7=>[1,1,1,3,$old,'Deleted'],
        8=>[1,1,1,1,$old,'Draft'],
        9=>[2,1,1,2,$old,'Other user'],
        10=>[null,1,1,2,$old,'Unassigned'],
        11=>[1,1,1,2,'2026-09-29 10:00:00','New'],
        12=>[1,1,4,2,$old,'Spam'],
        13=>[1,1,1,2,'2026-09-26 13:00:00','Boundary'],
    ];
    foreach ($fixtures as $id=>$f) {
        DB::table('conversations')->insert(['id'=>$id,'number'=>$id,'user_id'=>$f[0],'mailbox_id'=>$f[1],'status'=>$f[2],'state'=>$f[3],'created_at'=>$f[4],'updated_at'=>$now,'subject'=>$f[5]]);
    }
    DB::table('threads')->insert(['conversation_id'=>1,'state'=>2,'type'=>1,'created_at'=>'2026-09-29 12:00:00','updated_at'=>$now]);
    DB::table('threads')->insert(['conversation_id'=>2,'state'=>1,'type'=>1,'created_at'=>'2026-09-29 12:00:00','updated_at'=>$now]);
    $settings = Settings::defaults(); $settings['dd_timezone'] = 'America/Santiago';
    $builder = app(DigestBuilder::class);
    $digest = $builder->build(User::find(1), $settings, $now);
    check(array_column($digest['items'], 'number') === [1,2,13], 'active assigned conversations across mailboxes; state, access, age filters');
    check(rtrim($digest['items'][0]['url'], '?') === 'https://example.test/helpdesk/conversation/1', 'conversation link supports subdirectory installs');
    check($builder->build(User::find(3), $settings, $now)['total'] === 0, 'disabled user excluded');
    check($builder->build(User::find(4), $settings, $now)['total'] === 0, 'deleted user excluded');
    check($builder->build(User::find(5), $settings, $now)['total'] === 0, 'robot/team user excluded');
    $settings['dd_age_basis']='activity';
    check(array_column($builder->build(User::find(1), $settings, $now)['items'], 'number') === [2,13], 'inactivity uses published messages, ignores draft and updated_at');
    DB::table('threads')->insert(['conversation_id'=>2,'state'=>2,'type'=>3,'created_at'=>'2026-09-29 12:00:00','updated_at'=>$now]);
    check(array_column($builder->build(User::find(1), $settings, $now)['items'], 'number') === [13], 'published internal note resets inactivity');
    $settings['dd_age_basis']='created'; $settings['dd_max_items']=1;
    $limited = $builder->build(User::find(1), $settings, $now);
    check($limited['total']===3 && count($limited['items'])===1, 'row limit retains full count');
    $html = view('dailydigest::email', $limited)->render();
    check(strpos($html,'<script>') === false && strpos($html,'&lt;script&gt;') !== false, 'email escapes untrusted conversation subjects');
    check(strpos($html,'oldest 1 of 3') !== false, 'email discloses truncation');
    check(strpos(view('dailydigest::text', $digest)->render(), 'https://example.test/helpdesk/conversation/1') !== false, 'plain text part includes usable links');

    $settings['dd_enabled']='1'; $settings['dd_time']='09:00'; $settings['dd_days']=['1','2','3','4','5'];
    check(Settings::due($settings, Carbon::parse('2026-09-29 12:00:00','UTC')), 'Chilean daylight saving delivery time');
    check(!Settings::due($settings, Carbon::parse('2026-09-29 11:59:00','UTC')), 'not before scheduled time');
    check(Settings::due($settings, Carbon::parse('2026-06-29 13:00:00','UTC')), 'Chilean winter delivery time');
    check(!Settings::due($settings, Carbon::parse('2026-10-03 14:00:00','UTC')), 'excluded weekend');
    check(Settings::due($settings, Carbon::parse('2026-09-29 23:00:00','UTC')), 'same-day catch up');
    check(!Validator::make(['settings'=>$settings], Settings::rules())->fails(), 'valid settings accepted');
    $invalid=$settings; $invalid['dd_time']='25:61'; $invalid['dd_days']=[]; $invalid['dd_timezone']='Invalid/Zone';
    check(Validator::make(['settings'=>$invalid], Settings::rules())->fails(), 'invalid time, timezone and empty days rejected');

    $ledger = app(DeliveryLedger::class);
    check($ledger->claim(100,'2026-09-29',3), 'atomic delivery claim');
    check(!$ledger->claim(100,'2026-09-29',3), 'duplicate daily claim rejected');
    check($ledger->claim(100,'2026-09-30',3), 'next day allowed');
    $ledger->finish(100,'2026-09-29','failed_or_uncertain',3);
    check(!$ledger->claim(100,'2026-09-29',3), 'failed or uncertain attempt not duplicated');
    $ledger->claim(100,'2026-01-01',3); $ledger->prune('2026-07-01');
    check(!$ledger->exists(100,'2026-01-01') && $ledger->exists(100,'2026-09-29'), 'old ledger entries pruned without affecting current entries');


    // Exercise the actual mail integration, substituting only the final transport.
    $transport = new class extends Swift_NullTransport {
        public $messages=[];
        public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null) {
            $this->messages[]=clone $message;
            return parent::send($message, $failedRecipients);
        }
    };
    option('mail_driver','smtp'); option('mail_host','example.invalid'); option('mail_port',2525); option('mail_from','helpdesk@example.test');
    Eventy::addAction('mail.reapply_mail_config', function () use ($transport) { Mail::setSwiftMailer(new Swift_Mailer($transport)); });
    (new DigestMailer())->send($digest);
    check(count($transport->messages)===1, 'real mailer renders email through intercepted transport');
    $message=$transport->messages[0];
    check(array_keys($message->getTo())===['agent1@example.test'] && !$message->getCc() && !$message->getBcc(), 'mail sent only to assignee, no customer or copied recipients');
    check(array_keys($message->getFrom())===['helpdesk@example.test'], 'uses configured system sender');
    check(strpos($message->toString(),'text/plain')!==false && strpos($message->toString(),'text/html')!==false, 'multipart HTML and plain text email');
    check($message->getHeaders()->get('Auto-Submitted')->getFieldBody()==='auto-generated', 'automatic email header');
    $viewSettings=Settings::all();
    $settingsHtml=view('dailydigest::settings', ['settings'=>$viewSettings,'digest_users'=>User::where('type',1)->where('status',1)->get(),'digest_last_run'=>[], 'errors'=>new Illuminate\Support\ViewErrorBag()])->render();
    check(strpos($settingsHtml,'settings[dd_time]')!==false && strpos($settingsHtml,'daily-digest/preview')!==false, 'settings form and preview selector render');
    $request=Illuminate\Http\Request::create('/daily-digest/preview','GET',['user'=>'1']);
    $request->setUserResolver(function () { return User::find(1); });
    $blocked=false;
    try { (new Modules\DailyDigest\Http\Controllers\DigestController())->preview($request); }
    catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { $blocked=$e->getStatusCode()===403; }
    check($blocked,'non-admin blocked by preview controller');

    // Language changes must cover the subject and both email parts, then restore
    // the administrator/worker locale even when a send fails.
    $spanish = json_decode(file_get_contents(__DIR__.'/../Resources/lang/es.json'), true);
    check(is_array($spanish) && !empty($spanish), 'Spanish catalog loads');
    $allStrings=[];
    foreach (['Resources/views', 'Providers', 'Services'] as $folder) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../'.$folder)) as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4)==='.php') {
                preg_match_all("/__\\('([^']*)'/u", file_get_contents($file->getPathname()), $matches);
                $allStrings=array_merge($allStrings,$matches[1]);
            }
        }
    }
    check(!array_diff(array_unique($allStrings),array_keys($spanish)), 'Spanish catalog covers every module translation string');
    $placeholdersMatch=true;
    foreach ($spanish as $source=>$translated) {
        preg_match_all('/:[a-z_]+/', $source, $originalTokens);
        preg_match_all('/:[a-z_]+/', $translated, $translatedTokens);
        sort($originalTokens[0]); sort($translatedTokens[0]);
        $placeholdersMatch=$placeholdersMatch && $originalTokens[0]===$translatedTokens[0];
    }
    check($placeholdersMatch,'Spanish translations preserve all replacement tokens');
    app()->setLocale('es');
    check(Eventy::filter('settings.sections', [])['daily-digest']['title']==='Resumen diario', 'Spanish settings navigation');
    $spanishSettings=view('dailydigest::settings', ['settings'=>$viewSettings,'digest_users'=>User::where('type',1)->where('status',1)->get(),'digest_last_run'=>[], 'errors'=>new Illuminate\Support\ViewErrorBag()])->render();
    check(strpos($spanishSettings,'Envío diario')!==false && strpos($spanishSettings,'Hora de envío')!==false, 'Spanish settings form');
    app()->setLocale('en');
    $spanishDigest=$digest;
    $spanishDigest['user']=clone $digest['user'];
    $spanishDigest['user']->locale='es';
    (new DigestMailer())->send($spanishDigest);
    $spanishMessage=$transport->messages[count($transport->messages)-1];
    check($spanishMessage->getSubject()==='Recordatorio diario: 3 conversaciones activas', 'Spanish recipient gets Spanish email subject');
    $parts=array_merge([$spanishMessage],$spanishMessage->getChildren());
    $htmlSpanish=strpos($spanishMessage->getBody(),'Tus conversaciones activas')!==false && strpos($spanishMessage->getBody(),'lang="es"')!==false; $textSpanish=false;
    foreach ($parts as $part) {
        if ($part->getContentType()==='text/html') { $htmlSpanish=strpos($part->getBody(),'Tus conversaciones activas')!==false && strpos($part->getBody(),'lang="es"')!==false; }
        if ($part->getContentType()==='text/plain') { $textSpanish=strpos($part->getBody(),'Tus conversaciones activas')!==false; }
    }
    check($htmlSpanish && $textSpanish, 'Spanish HTML and plain text parts');
    check(app()->getLocale()==='en','successful send restores worker language');
    $spanishPreview=Modules\DailyDigest\Services\RecipientLocale::run($spanishDigest['user'], function () use ($spanishDigest) { return view('dailydigest::email',$spanishDigest)->render(); });
    check(strpos($spanishPreview,'Tus conversaciones activas')!==false && app()->getLocale()==='en', 'preview uses recipient language and restores administrator language');
    app()->setLocale('es');
    (new DigestMailer())->send($digest);
    check($transport->messages[count($transport->messages)-1]->getSubject()==='Daily reminder: 3 active conversations' && app()->getLocale()==='es','English recipient remains English in a Spanish batch');
    app()->setLocale('en');

    $expectedEnglishHtml=view('dailydigest::email', $digest)->render();
    $expectedEnglishText=view('dailydigest::text', $digest)->render();
    foreach (['fr', 'de', 'pt-BR', 'zz'] as $unsupportedLocale) {
        $fallbackDigest=$digest;
        $fallbackDigest['user']=clone $digest['user'];
        $fallbackDigest['user']->locale=$unsupportedLocale;
        app()->setLocale('es');
        (new DigestMailer())->send($fallbackDigest);
        $fallbackMessage=$transport->messages[count($transport->messages)-1];
        check($fallbackMessage->getSubject()==='Daily reminder: 3 active conversations', $unsupportedLocale.' recipient gets English subject');
        $fallbackText=null;
        foreach ($fallbackMessage->getChildren() as $part) {
            if ($part->getContentType()==='text/plain') { $fallbackText=$part->getBody(); }
        }
        check($fallbackMessage->getBody()===$expectedEnglishHtml && $fallbackText===$expectedEnglishText, $unsupportedLocale.' recipient gets fully English HTML and plain text');
        $fallbackPreview=Modules\DailyDigest\Services\RecipientLocale::run($fallbackDigest['user'], function () use ($fallbackDigest) { return view('dailydigest::email',$fallbackDigest)->render(); });
        check($fallbackPreview===$expectedEnglishHtml, $unsupportedLocale.' preview also falls back to English');
        check(app()->getLocale()==='es' && $fallbackDigest['user']->locale===$unsupportedLocale, $unsupportedLocale.' fallback preserves worker and profile language');
    }
    app()->setLocale('en');

    $failingLocalizedMailer=new class extends DigestMailer { protected function sendLocalized(array $digest) { throw new RuntimeException('Simulated localized failure'); } };
    try { $failingLocalizedMailer->send($spanishDigest); } catch (RuntimeException $e) {}
    check(app()->getLocale()==='en','failed send restores worker language');

    // Save settings through the core controller, exactly as the module UI does.
    $saveRequest=Illuminate\Http\Request::create('/app-settings/daily-digest','POST',['settings'=>$settings]);
    $saveRequest->setLaravelSession(app('session')->driver());
    $app->instance('request',$saveRequest);
    (new App\Http\Controllers\SettingsController())->save('daily-digest');
    Option::$cache=[];
    check(Settings::all()['dd_timezone']==='America/Santiago' && Settings::all()['dd_days']===['1','2','3','4','5'], 'core settings controller persists module configuration');
    option('dd_enabled','0');

    $fake = new class extends DigestMailer { public $sent=[]; public $fail=false; public function send(array $digest) { if($this->fail) { throw new RuntimeException('Simulated transport failure'); } $this->sent[]=$digest; } };
    $app->instance(DigestMailer::class,$fake);
    check(Artisan::call('freescout:daily-digest',['--preview'=>true,'--user'=>'1'])===0 && count($fake->sent)===0 && !$ledger->exists(1,'2026-09-29'), 'preview sends nothing and claims nothing');
    check(Artisan::call('freescout:daily-digest',['--send-now'=>true,'--user'=>'1'])===0 && count($fake->sent)===0, 'disabled by default even for send-now');
    foreach ($settings as $key=>$value) { option($key,$value); }
    check(Artisan::call('freescout:daily-digest',['--user'=>'1'])===0 && count($fake->sent)===1, 'scheduled command sends one digest');
    Artisan::call('freescout:daily-digest',['--send-now'=>true,'--user'=>'1']);
    check(count($fake->sent)===1, 'second scheduler/manual run does not duplicate');
    Carbon::setTestNow($now->copy()->addDay()); Artisan::call('freescout:daily-digest',['--user'=>'1']);
    check(count($fake->sent)===2, 'same outstanding conversations repeat next day');
    Carbon::setTestNow($now->copy()->addDays(2));
    DB::table('conversations')->where('user_id',1)->update(['status'=>3]);
    Artisan::call('freescout:daily-digest',['--user'=>'1']);
    check(count($fake->sent)===2, 'closing conversations stops digest and skips empty emails');
    $fake->fail=true;
    check(Artisan::call('freescout:daily-digest',['--user'=>'2'])===1 && $ledger->exists(2,'2026-10-01'), 'transport failure recorded with nonzero exit');
    $fake->fail=false; Artisan::call('freescout:daily-digest',['--user'=>'2']);
    check(count($fake->sent)===2, 'failed send not automatically retried same day');
    check(Artisan::call('freescout:daily-digest',['--preview'=>true,'--user'=>'abc'])===1, 'malformed user filter rejected');
    check(Artisan::call('freescout:daily-digest',['--preview'=>true,'--user'=>'999'])===1, 'missing user reported');
    // Large backlogs cross the internal query chunk boundary.
    for($id=1000;$id<1205;$id++) { DB::table('conversations')->insert(['id'=>$id,'number'=>$id,'user_id'=>2,'mailbox_id'=>1,'status'=>1,'state'=>2,'created_at'=>$old,'updated_at'=>$now,'subject'=>'Backlog '.$id]); }
    $settings['dd_max_items']=200;
    $large=$builder->build(User::find(2),$settings,$now);
    check($large['total']===206 && count($large['items'])===200, 'chunked backlog counted without duplicate or missing rows');
    if (getenv('DIGEST_PREVIEW_OUTPUT')) {
        $demo=$digest; $demo['user']->first_name='Andrea'; $demo['user']->last_name='';
        $demo['items'][0]['subject']='Purchase order awaiting confirmation';
        $demo['items'][1]['subject']='Invoice reconciliation'; $demo['items'][2]['subject']='Service request follow-up';
        file_put_contents(getenv('DIGEST_PREVIEW_OUTPUT'), view('dailydigest::email',$demo)->render());
    }
    echo "\n$checks checks passed; all email delivery was intercepted.\n";
} catch (Throwable $e) { fwrite(STDERR, $e."\n"); exit(1); }
