@extends('layouts.guest')

@section('title', 'Verify Email - Temp Mail')

@section('content')
    <div class="verify-email-icon">📬</div>
    <h1 class="auth-title">Check Your Email</h1>
    <p class="auth-subtitle">We've sent a verification link to:</p>

    <div class="email-highlight">
        {{ session('email', 'your email address') }}
    </div>

    <div class="verify-instructions">
        <p>Click the link in the email to verify your account and activate it.</p>
        <p class="hint">The link will expire in 24 hours.</p>
    </div>

    <div class="verify-tips">
        <h4>Didn't receive the email?</h4>
        <ul>
            <li>Check your spam or junk folder</li>
            <li>Make sure you entered the correct email</li>
            <li>Wait a few minutes and try again</li>
        </ul>
    </div>
@endsection

@section('footer')
    <p><a href="{{ route('register') }}">← Register with different email</a></p>
@endsection