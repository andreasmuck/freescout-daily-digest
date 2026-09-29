<?php

namespace Modules\DailyDigest\Services;

use App\Option;
use Carbon\Carbon;

class Settings
{
    public static function defaults()
    {
        return [
            'dd_enabled' => '0',
            'dd_time' => '09:00',
            'dd_timezone' => config('app.timezone', 'UTC'),
            'dd_days' => ['1', '2', '3', '4', '5', '6', '7'],
            'dd_min_days' => 3,
            'dd_age_basis' => 'created',
            'dd_max_items' => 200,
        ];
    }

    public static function all()
    {
        $settings = [];
        foreach (self::defaults() as $key => $default) {
            $settings[$key] = Option::get($key, $default);
        }
        return $settings;
    }

    public static function rules()
    {
        return [
            'settings.dd_enabled' => 'required|in:0,1',
            'settings.dd_time' => 'required|date_format:H:i',
            'settings.dd_timezone' => 'required|timezone',
            'settings.dd_days' => 'required|array|min:1',
            'settings.dd_days.*' => 'required|integer|between:1,7',
            'settings.dd_min_days' => 'required|integer|between:0,3650',
            'settings.dd_age_basis' => 'required|in:created,activity',
            'settings.dd_max_items' => 'required|integer|between:1,500',
        ];
    }

    public static function due(array $settings, Carbon $now)
    {
        $local = $now->copy()->setTimezone($settings['dd_timezone']);
        return (bool) $settings['dd_enabled']
            && in_array((string) $local->format('N'), (array) $settings['dd_days'], true)
            && $local->format('H:i') >= $settings['dd_time'];
    }
}
