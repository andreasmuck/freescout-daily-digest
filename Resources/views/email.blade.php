<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Your active conversations') }}</title></head>
<body style="margin:0;padding:24px 12px;background:#f3f5f7;color:#243247;font-family:Arial,sans-serif;">
<div style="max-width:900px;margin:0 auto;background:white;padding:24px;border:1px solid #dde3e9;border-radius:8px;">
    <p style="margin:0 0 8px;color:#68778b;font-size:13px;">{{ config('app.name', 'FreeScout') }} · {{ $date }}</p>
    <h1 style="font-size:24px;margin:0 0 18px;">{{ __('Your active conversations') }}</h1>
    <p>{{ __('Hello :name,', ['name' => $user->getFullName()]) }}</p>
    <p>{{ __('You have :count active conversations matching your team’s reminder settings.', ['count' => $total]) }}
    {{ __('Please review them, close those that are resolved, and follow up on anything still outstanding.') }}</p>
    <p style="font-size:13px;color:#68778b;">
    @if ($settings['dd_age_basis'] === 'activity')
        {{ __('Included after :days days without a published message or internal note.', ['days' => $settings['dd_min_days']]) }}
    @else
        {{ __('Included once created at least :days days ago, even if there has been recent activity.', ['days' => $settings['dd_min_days']]) }}
    @endif
    </p>
    @if ($total)
    <table style="width:100%;border-collapse:collapse;font-size:14px;">
        <thead><tr style="background:#edf2f7;text-align:left;">
            <th style="padding:10px;">{{ __('Conversation') }}</th><th style="padding:10px;">{{ __('Mailbox') }}</th><th style="padding:10px;">{{ __('Age') }}</th><th style="padding:10px;">{{ __('Last activity') }}</th>
        </tr></thead>
        <tbody>@foreach ($items as $item)<tr>
            <td style="padding:10px;border-bottom:1px solid #e5e9ee;overflow-wrap:anywhere;"><a style="color:#1765ad;" href="{{ $item['url'] }}">#{{ $item['number'] }} — {{ $item['subject'] }}</a></td>
            <td style="padding:10px;border-bottom:1px solid #e5e9ee;">{{ $item['mailbox'] }}</td>
            <td style="padding:10px;border-bottom:1px solid #e5e9ee;">{{ __(':days days', ['days' => $item['age']]) }}</td>
            <td style="padding:10px;border-bottom:1px solid #e5e9ee;">{{ $item['last_activity'] }}</td>
        </tr>@endforeach</tbody>
    </table>
    @else
        <p>{{ __('No conversations currently match. No email would be sent.') }}</p>
    @endif
    @if ($total > count($items))
        <p><strong>{{ __('Showing the oldest :shown of :total conversations.', ['shown' => count($items), 'total' => $total]) }}</strong> {{ __('Open FreeScout to review the rest in your assigned folders.') }}</p>
    @endif
    <p style="margin-top:24px;"><a href="{{ url('/') }}" style="display:inline-block;background:#1765ad;color:white;padding:12px 18px;border-radius:4px;text-decoration:none;">{{ __('Open FreeScout') }}</a></p>
    <p style="font-size:12px;color:#68778b;">{{ __('Times shown in :zone. Age is measured from creation.', ['zone' => $settings['dd_timezone']]) }}<br>
    {{ __('Matching conversations remain in each daily digest until their status, assignment, access, or age eligibility changes. This email does not change any conversation.') }}</p>
</div></body></html>
