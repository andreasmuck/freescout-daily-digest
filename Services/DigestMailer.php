<?php

namespace Modules\DailyDigest\Services;

class DigestMailer
{
    public function send(array $digest)
    {
        return RecipientLocale::run($digest['user'], function () use ($digest) {
            return $this->sendLocalized($digest);
        });
    }

    protected function sendLocalized(array $digest)
    {
        \MailHelper::setSystemMailDriver();
        $driver = config('mail.driver');
        if (!in_array($driver, ['smtp', 'sendmail', 'mail', 'mailgun', 'ses', 'sparkpost'], true)) {
            throw new \RuntimeException('Daily Digest requires a delivering system mail driver.');
        }
        \Mail::send(['html' => 'dailydigest::email', 'text' => 'dailydigest::text'], $digest, function ($message) use ($digest) {
            $message->to($digest['user']->email, $digest['user']->getFullName())
                ->subject(__('Daily reminder: :count active conversations', ['count' => $digest['total']]));
            $message->getSwiftMessage()->getHeaders()->addTextHeader('Auto-Submitted', 'auto-generated');
        });
        if (count(\Mail::failures())) {
            throw new \RuntimeException('Daily Digest recipient was rejected by the mail transport.');
        }
    }
}
