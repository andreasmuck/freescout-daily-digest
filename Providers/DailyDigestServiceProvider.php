<?php

namespace Modules\DailyDigest\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\DailyDigest\Services\Settings;
use Modules\DailyDigest\Console\SendDigest;

class DailyDigestServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([SendDigest::class]);
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'dailydigest');

        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections['daily-digest'] = ['title' => __('Daily Digest'), 'icon' => 'envelope', 'order' => 350];
            return $sections;
        });
        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            return $section === 'daily-digest' ? Settings::all() : $settings;
        }, 20, 2);
        \Eventy::addFilter('settings.section_params', function ($params, $section) {
            if ($section === 'daily-digest') {
                $params['validator_rules'] = Settings::rules();
                $params['template_vars'] = [
                    'digest_users' => \App\User::where('status', \App\User::STATUS_ACTIVE)->where('type', \App\User::TYPE_USER)
                        ->orderBy('first_name')->get(),
                    'digest_last_run' => \App\Option::get('dd_last_run', []),
                ];
            }
            return $params;
        }, 20, 2);
        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section === 'daily-digest' ? 'dailydigest::settings' : $view;
        }, 20, 2);
        \Eventy::addFilter('schedule', function ($schedule) {
            $schedule->command('freescout:daily-digest')->everyFiveMinutes()->withoutOverlapping();
            return $schedule;
        });
    }
}
