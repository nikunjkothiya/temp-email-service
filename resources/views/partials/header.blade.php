<header class="header">
    <div class="header-content">
        <a href="{{ route('home') }}" class="header-logo">
            <span class="logo-icon">📧</span>
            <span class="logo-text">Temp Mail</span>
        </a>

        <p class="header-tagline">Disposable email addresses. Receive emails instantly.</p>

        <nav class="header-nav">
            @auth
                <span class="user-info">
                    <span class="user-badge">✓ Authenticated</span>
                    <span class="user-name">{{ Auth::user()->name }}</span>
                </span>
                <form action="{{ route('logout') }}" method="POST" class="logout-form">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Logout</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="btn btn-secondary btn-sm">Login</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Register</a>
            @endauth
        </nav>
    </div>

    @auth
        <div class="auth-benefit">
            <span class="benefit-icon">⭐</span>
            <span>Your inbox lasts 1 week!</span>
        </div>
    @else
        <div class="guest-notice">
            <span class="notice-icon">ℹ️</span>
            <span>Guest inbox expires in 1 hour. <a href="{{ route('register') }}">Register</a> for 1 week retention!</span>
        </div>
    @endauth
</header>