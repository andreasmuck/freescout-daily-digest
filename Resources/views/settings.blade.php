<p>{{ __('Send each user one daily email covering their assigned active conversations across all accessible, active mailboxes.') }}</p>
@if ($errors->any())
    <div class="alert alert-danger"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form class="form-horizontal margin-top" method="POST" action="{{ route('settings.save', ['section' => 'daily-digest']) }}">
    {{ csrf_field() }}
    <div class="form-group">
        <label for="dd_enabled" class="col-sm-3 control-label">{{ __('Daily delivery') }}</label>
        <div class="col-sm-7"><select id="dd_enabled" name="settings[dd_enabled]" class="form-control">
            <option value="0" @if (!old('settings.dd_enabled', $settings['dd_enabled'])) selected @endif>{{ __('Disabled') }}</option>
            <option value="1" @if (old('settings.dd_enabled', $settings['dd_enabled'])) selected @endif>{{ __('Enabled') }}</option>
        </select></div>
    </div>
    <div class="form-group">
        <label for="dd_time" class="col-sm-3 control-label">{{ __('Delivery time') }}</label>
        <div class="col-sm-7">
        <input id="dd_time" type="text" required name="settings[dd_time]" value="{{ old('settings.dd_time', $settings['dd_time']) }}" class="form-control" placeholder="HH:MM" pattern="([01][0-9]|2[0-3]):[0-5][0-9]" aria-describedby="dd_time_help" autocomplete="off">
        <p id="dd_time_help" class="help-block">{{ __('Delivery starts within five minutes of this time. If the scheduler was unavailable, it catches up later the same day.') }}</p></div>
    </div>
    <div class="form-group">
        <label for="dd_timezone" class="col-sm-3 control-label">{{ __('Time zone') }}</label>
        <div class="col-sm-7"><select id="dd_timezone" name="settings[dd_timezone]" class="form-control">
            @foreach (\DateTimeZone::listIdentifiers() as $zone)
                <option value="{{ $zone }}" @if (old('settings.dd_timezone', $settings['dd_timezone']) === $zone) selected @endif>{{ $zone }}</option>
            @endforeach
        </select></div>
    </div>
    <div class="form-group">
        <label class="col-sm-3 control-label">{{ __('Delivery days') }}</label>
        <div class="col-sm-7">
            @foreach ([1 => __('Monday'), 2 => __('Tuesday'), 3 => __('Wednesday'), 4 => __('Thursday'), 5 => __('Friday'), 6 => __('Saturday'), 7 => __('Sunday')] as $day => $label)
                <div class="checkbox"><label><input type="checkbox" name="settings[dd_days][]" value="{{ $day }}" @if (in_array((string) $day, (array) old('settings.dd_days', $settings['dd_days']))) checked @endif> {{ $label }}</label></div>
            @endforeach
        </div>
    </div>
    <div class="form-group">
        <label for="dd_min_days" class="col-sm-3 control-label">{{ __('Minimum age (days)') }}</label>
        <div class="col-sm-7"><input id="dd_min_days" type="number" min="0" max="3650" required name="settings[dd_min_days]" value="{{ old('settings.dd_min_days', $settings['dd_min_days']) }}" class="form-control">
        <p class="help-block">{{ __('Use 0 to include all active conversations. Days are elapsed 24-hour periods, including weekends.') }}</p></div>
    </div>
    <div class="form-group">
        <label for="dd_age_basis" class="col-sm-3 control-label">{{ __('Measure age from') }}</label>
        <div class="col-sm-7"><select id="dd_age_basis" name="settings[dd_age_basis]" class="form-control">
            <option value="created" @if (old('settings.dd_age_basis', $settings['dd_age_basis']) === 'created') selected @endif>{{ __('Conversation creation') }}</option>
            <option value="activity" @if (old('settings.dd_age_basis', $settings['dd_age_basis']) === 'activity') selected @endif>{{ __('Last message or internal note') }}</option>
        </select><p class="help-block">{{ __('Creation age includes conversations still being discussed. Last activity includes published replies and notes; status changes and views do not reset it. Neither measures continuous time in Active status.') }}</p></div>
    </div>
    <div class="form-group">
        <label for="dd_max_items" class="col-sm-3 control-label">{{ __('Maximum rows per email') }}</label>
        <div class="col-sm-7"><input id="dd_max_items" type="number" min="1" max="500" required name="settings[dd_max_items]" value="{{ old('settings.dd_max_items', $settings['dd_max_items']) }}" class="form-control">
        <p class="help-block">{{ __('Oldest conversations appear first. The email always shows the full eligible count and explains when the list is truncated.') }}</p></div>
    </div>
    <div class="form-group"><div class="col-sm-7 col-sm-offset-3"><button class="btn btn-primary" type="submit">{{ __('Save') }}</button></div></div>
</form>
<hr>
<h3>{{ __('Preview without sending') }}</h3>
<p>{{ __('Save your settings first. Preview works while delivery is disabled and does not record a delivery.') }}</p>
<form method="GET" action="{{ route('dailydigest.preview') }}" target="_blank" class="form-inline">
    <label for="digest_user">{{ __('User') }}</label>
    <select id="digest_user" name="user" class="form-control" required>
        @foreach ($digest_users as $digest_user)<option value="{{ $digest_user->id }}">{{ $digest_user->getFullName() }} ({{ $digest_user->email }})</option>@endforeach
    </select>
    <button type="submit" class="btn btn-default">{{ __('Preview digest') }}</button>
</form>
<hr>
<p>{{ __('Uses Manage → Settings → Mail Settings and the existing FreeScout scheduler. Empty digests are skipped. Unassigned conversations and team-only assignments are not included.') }}</p>
@if ($digest_last_run)
    <p><strong>{{ __('Last recorded batch') }}:</strong> {{ $digest_last_run['at'] }}<br>
    {{ __('Accepted by mail transport') }}: {{ $digest_last_run['accepted'] }} · {{ __('Failed or uncertain') }}: {{ $digest_last_run['failed'] }}</p>
    <p class="help-block">{{ __('Transport acceptance does not confirm inbox delivery. Failed or interrupted sends are not retried automatically that day; check App Logs and your mail provider logs.') }}</p>
@endif

@include('partials/include_datepicker')

@section('javascript')
    @parent
    flatpickr('#dd_time', {
        enableTime: true,
        noCalendar: true,
        dateFormat: 'H:i',
        time_24hr: true,
        minuteIncrement: 1,
        allowInput: true,
        disableMobile: true
    });
@endsection
