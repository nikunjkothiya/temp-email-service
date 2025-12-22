@extends('layouts.guest')

@section('title', 'Register - Temp Mail')

@section('content')
    <h1 class="auth-title">Create Account</h1>
    <p class="auth-subtitle">Register to keep your inbox for 1 week!</p>

    <form method="POST" action="{{ route('register') }}" class="auth-form">
        @csrf

        <div class="form-group">
            <label for="name">Full Name</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="John Doe" required autofocus>
            @error('name')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required>
            @error('email')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Minimum 8 characters" required>
            @error('password')
                <span class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <input type="password" id="password_confirmation" name="password_confirmation"
                placeholder="Confirm your password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">
            Create Account
        </button>
    </form>
@endsection

@section('footer')
    <p>Already have an account? <a href="{{ route('login') }}">Login</a></p>
@endsection