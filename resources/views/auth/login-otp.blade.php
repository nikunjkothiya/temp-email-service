@extends('layouts.guest')

@section('title', 'Verify OTP - Temp Mail')

@section('content')
    <h1 class="auth-title">Enter Verification Code</h1>
    <p class="auth-subtitle">We sent a 6-digit code to your email</p>

    <form method="POST" action="{{ route('login.otp.verify') }}" class="auth-form">
        @csrf

        <div class="form-group">
            <label for="otp">One-Time Password</label>
            <input type="text" id="otp" name="otp" placeholder="000000" maxlength="6" pattern="[0-9]{6}" class="otp-input"
                required autofocus autocomplete="one-time-code">
            @error('otp')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-full">
            Verify & Login
        </button>
    </form>

    <div class="resend-section">
        <p>Didn't receive the code?</p>
        <form method="POST" action="{{ route('login.resend-otp') }}" class="resend-form">
            @csrf
            <button type="submit" class="btn-link">Resend OTP</button>
        </form>
    </div>
@endsection

@section('footer')
    <p><a href="{{ route('login') }}">← Back to Login</a></p>
@endsection