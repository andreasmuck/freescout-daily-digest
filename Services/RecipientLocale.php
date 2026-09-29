<?php

namespace Modules\DailyDigest\Services;

use App\User;

class RecipientLocale
{
    public static function run(User $user, callable $callback)
    {
        $previous = app()->getLocale();
        try {
            $locale = $user->getLocale();
            // Other FreeScout locales translate shared labels but not this module's
            // text, producing mixed-language emails unless we explicitly fall back.
            app()->setLocale(in_array($locale, ['en', 'es'], true) ? $locale : 'en');
            return $callback();
        } finally {
            // A scheduler batch may contain users with different languages.
            app()->setLocale($previous);
        }
    }
}
