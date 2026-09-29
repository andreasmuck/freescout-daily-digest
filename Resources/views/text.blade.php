{{ __('Your active conversations') }} — {{ $date }}

{{ __('Hello :name,', ['name' => $user->getFullName()]) }}
{{ __('You have :count active conversations matching your team’s reminder settings.', ['count' => $total]) }}
{{ __('Please review them, close those that are resolved, and follow up on anything still outstanding.') }}

@foreach ($items as $item)
#{{ $item['number'] }} — {!! str_replace(["\r", "\n"], ' ', $item['subject']) !!}
{!! str_replace(["\r", "\n"], ' ', $item['mailbox']) !!} | {{ __(':days days', ['days' => $item['age']]) }} | {{ $item['last_activity'] }}
{!! $item['url'] !!}

@endforeach
@if ($total > count($items))
{{ __('Showing the oldest :shown of :total conversations.', ['shown' => count($items), 'total' => $total]) }}
@endif
{{ __('Open FreeScout') }}: {!! url('/') !!}
{{ __('Times shown in :zone. Age is measured from creation.', ['zone' => $settings['dd_timezone']]) }}
