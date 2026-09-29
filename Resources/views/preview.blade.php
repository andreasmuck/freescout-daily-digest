@extends('layouts.app')
@section('title', __('Daily Digest Preview'))
@section('content')
<div style="padding:20px;">
    <h1>{{ __('Daily Digest Preview') }}</h1>
    <div class="alert alert-info">{{ __('Preview only. No email has been sent.') }} {{ __('Recipient') }}: {{ $digest['user']->email }}</div>
    <p><a href="{{ route('settings', ['section' => 'daily-digest']) }}">{{ __('Back to Daily Digest settings') }}</a></p>
    <iframe title="{{ __('Email preview') }}" sandbox="" style="width:100%;height:720px;border:1px solid #ddd;" srcdoc="{{ view('dailydigest::email', $digest)->render() }}"></iframe>
</div>
@endsection
