<?php

namespace Modules\DailyDigest\Services;

use App\Conversation;
use App\Thread;
use App\User;
use Carbon\Carbon;

class DigestBuilder
{
    public function build(User $user, array $settings, Carbon $now)
    {
        $digest = ['user' => $user, 'items' => [], 'total' => 0, 'settings' => $settings,
            'date' => $now->copy()->setTimezone($settings['dd_timezone'])->format('Y-m-d')];
        if (!$user->isActive() || $user->type != User::TYPE_USER) {
            return $digest;
        }

        $mailboxes = $user->mailboxesCanView()->filter(function ($mailbox) {
            return $mailbox->isActive();
        });
        $cutoff = $now->copy()->setTimezone('UTC')->subDays((int) $settings['dd_min_days']);
        $query = Conversation::where('user_id', $user->id)
            ->where('status', Conversation::STATUS_ACTIVE)
            ->where('state', Conversation::STATE_PUBLISHED)
            ->whereIn('mailbox_id', $mailboxes->pluck('id')->all())
            ->where('created_at', '<=', $cutoff)
            ->with('mailbox')->select('conversations.*');

        // updated_at also changes on housekeeping operations. Use published thread dates
        // for inactivity, including internal notes, instead of the waiting-since field.
        if ($settings['dd_age_basis'] === 'activity') {
            $query->whereDoesntHave('threads', function ($threads) use ($cutoff) {
                $threads->where('state', Thread::STATE_PUBLISHED)->where('created_at', '>', $cutoff);
            });
        }
        $lastActivity = Thread::selectRaw('MAX(created_at)')
            ->whereColumn('threads.conversation_id', 'conversations.id')
            ->where('state', Thread::STATE_PUBLISHED);
        $query->selectSub($lastActivity->toBase(), 'digest_last_activity');

        $query->orderBy('created_at')->orderBy('id')->chunk(200, function ($conversations) use (&$digest, $user, $settings, $now) {
            foreach ($conversations as $conversation) {
                if (!\Gate::forUser($user)->allows('view', $conversation)) {
                    continue;
                }
                $digest['total']++;
                if (count($digest['items']) >= (int) $settings['dd_max_items']) {
                    continue;
                }
                $activity = Carbon::parse($conversation->digest_last_activity ?: $conversation->created_at, 'UTC');
                $digest['items'][] = [
                    'number' => $conversation->number,
                    'subject' => $conversation->subject,
                    'mailbox' => $conversation->mailbox->name,
                    'age' => (int) $conversation->created_at->diffInDays($now),
                    'last_activity' => $activity->setTimezone($settings['dd_timezone'])->format('Y-m-d H:i'),
                    'url' => Conversation::conversationUrl($conversation->id),
                ];
            }
        });
        return $digest;
    }
}
