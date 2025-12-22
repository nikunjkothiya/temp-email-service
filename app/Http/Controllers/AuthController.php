<?php

namespace App\Http\Controllers;

use App\Mail\LoginOtpMail;
use App\Mail\VerifyEmailMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show registration form.
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Handle registration.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Generate verification token and send email
        $token = $user->generateVerificationToken();
        $verificationUrl = route('verify-email', ['token' => $token]);
        
        Mail::to($user->email)->send(new VerifyEmailMail($user, $verificationUrl));

        return redirect()->route('verify-email.notice')
            ->with('email', $user->email);
    }

    /**
     * Show verification notice page.
     */
    public function showVerifyEmailNotice(): View
    {
        return view('auth.verify-email-notice');
    }

    /**
     * Handle email verification link.
     */
    public function verifyEmail(string $token): RedirectResponse
    {
        $user = User::findByVerificationToken($token);

        if (!$user) {
            return redirect()->route('login')
                ->with('error', 'Invalid or expired verification link.');
        }

        $user->markEmailAsVerified();

        return redirect()->route('login')
            ->with('success', 'Email verified successfully! You can now log in.');
    }

    /**
     * Show login form.
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle login (step 1: validate credentials, send OTP).
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
        }

        if (!$user->hasVerifiedEmail()) {
            return back()->withErrors(['email' => 'Please verify your email first.'])->withInput();
        }

        // Generate OTP and send via email
        $otp = OtpCode::generate($user, 'login');
        Mail::to($user->email)->send(new LoginOtpMail($user, $otp->code));

        // Store user ID in session for OTP verification
        session(['login_user_id' => $user->id]);

        return redirect()->route('login.otp');
    }

    /**
     * Show OTP verification form.
     */
    public function showLoginOtp(): View|RedirectResponse
    {
        if (!session('login_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.login-otp');
    }

    /**
     * Verify OTP and complete login.
     */
    public function verifyLoginOtp(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => 'required|string|size:6',
        ]);

        $userId = session('login_user_id');
        if (!$userId) {
            return redirect()->route('login')
                ->with('error', 'Session expired. Please log in again.');
        }

        $user = User::find($userId);
        if (!$user) {
            session()->forget('login_user_id');
            return redirect()->route('login')
                ->with('error', 'User not found.');
        }

        $otp = OtpCode::verify($user, $validated['otp'], 'login');
        if (!$otp) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        // Mark OTP as used and log in user
        $otp->markAsUsed();
        session()->forget('login_user_id');
        Auth::login($user, true);

        return redirect()->route('home')
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }

    /**
     * Resend login OTP.
     */
    public function resendLoginOtp(): RedirectResponse
    {
        $userId = session('login_user_id');
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            return redirect()->route('login');
        }

        $otp = OtpCode::generate($user, 'login');
        Mail::to($user->email)->send(new LoginOtpMail($user, $otp->code));

        return back()->with('success', 'A new OTP has been sent to your email.');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
