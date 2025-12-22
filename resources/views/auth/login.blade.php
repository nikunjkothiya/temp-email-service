@extends('layouts.guest')

@section('title', 'Login - Temp Mail')

@section('content')
    <h1 class="auth-title">Welcome Back</h1>
    <p class="auth-subtitle">Login to access your extended inbox</p>

    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required
                autofocus>
            @error('email')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Your password" required>
            @error('password')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary btn-full">
            Continue
        </button>

        <p class="form-hint">
            <span class="hint-icon">🔐</span>
            A one-time code will be sent to your email for verification.
        </p>
    </form>
@endsection

@section('footer')
    <p>Don't have an account? <a href="{{ route('register') }}">Register</a></p>
@endsection