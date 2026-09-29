<?php

namespace Modules\DailyDigest\Console;

use App\Option;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\DailyDigest\Services\Settings;
use Modules\DailyDigest\Services\DigestBuilder;
use Modules\DailyDigest\Services\DigestMailer;
use Modules\DailyDigest\Services\DeliveryLedger;

class SendDigest extends Command
{
    protected $signature = 'freescout:daily-digest
        {--preview : List counts without sending or recording deliveries}
        {--user= : Restrict to one active user ID}
        {--send-now : Ignore delivery time and weekdays; still requires enabled setting}';
    protected $description = 'Send one daily digest per user of aging active conversations';

    public function handle()
    {
        $settings = Settings::all();
        $now = Carbon::now('UTC');
        $preview = (bool) $this->option('preview');
        $userId = $this->option('user');
        if ($userId !== null && (!ctype_digit((string) $userId) || (int) $userId < 1)) {
            $this->error('--user must be a positive user ID.');
            return 1;
        }
        if (!$preview && (!$settings['dd_enabled'] || (!$this->option('send-now') && !Settings::due($settings, $now)))) {
            $this->info('Daily Digest is disabled or not due.');
            return 0;
        }

        $builder = app(DigestBuilder::class);
        $ledger = app(DeliveryLedger::class);
        $date = $now->copy()->setTimezone($settings['dd_timezone'])->format('Y-m-d');
        $users = User::where('status', User::STATUS_ACTIVE)->where('type', User::TYPE_USER);
        if ($userId !== null) {
            $users->where('id', (int) $userId);
        }
        $summary = ['at' => $now->toIso8601String(), 'accepted' => 0, 'failed' => 0, 'empty' => 0, 'skipped' => 0];
        $found = 0;
        $users->orderBy('id')->chunk(100, function ($users) use ($builder, $ledger, $settings, $now, $date, $preview, &$summary, &$found) {
            foreach ($users as $user) {
                $found++;
                if (!$preview && $ledger->exists($user->id, $date)) {
                    $summary['skipped']++;
                    continue;
                }
                $claimed = false;
                $count = 0;
                try {
                    $digest = $builder->build($user, $settings, $now);
                    $count = $digest['total'];
                    if ($preview) {
                        $this->line('User '.$user->id.': '.$count.' eligible conversation(s). No email sent.');
                        continue;
                    }
                    if (!$count) {
                        $summary['empty']++;
                        continue;
                    }
                    if (!$ledger->claim($user->id, $date, $count)) {
                        $summary['skipped']++;
                        continue;
                    }
                    $claimed = true;
                    app(DigestMailer::class)->send($digest);
                    $ledger->finish($user->id, $date, 'accepted', $count);
                    $summary['accepted']++;
                } catch (\Throwable $e) {
                    $summary['failed']++;
                    if ($claimed) {
                        $ledger->finish($user->id, $date, 'failed_or_uncertain', $count);
                    }
                    // Do not log subjects, customer details, or transport exception text.
                    \Log::error('Daily Digest failed for user '.$user->id.' on '.$date.' ('.get_class($e).'). Check system mail settings and provider logs.');
                    $this->error('Daily Digest failed for user '.$user->id.'. Check App Logs and mail delivery logs.');
                }
            }
        });
        if ($userId !== null && !$found) {
            $this->error('No active user has that ID.');
            return 1;
        }
        if (!$preview) {
            // Preserve the last meaningful batch instead of replacing it every five minutes.
            if ($summary['accepted'] || $summary['failed'] || !Option::get('dd_last_run', [])) {
                Option::set('dd_last_run', $summary);
            }
            $ledger->prune($now->copy()->subDays(90)->format('Y-m-d'));
            $this->info('Accepted: '.$summary['accepted'].'; failed: '.$summary['failed'].'; empty: '.$summary['empty'].'; already attempted today: '.$summary['skipped']);
        }
        return $summary['failed'] ? 1 : 0;
    }
}
