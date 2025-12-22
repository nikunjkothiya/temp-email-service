<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Home route
Route::get('/', function () {
    return view('app');
})->name('home');

// Authentication Routes
Route::middleware('guest')->group(function () {
    // Registration
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    
    // Email Verification
    Route::get('/verify-email/notice', [AuthController::class, 'showVerifyEmailNotice'])->name('verify-email.notice');
    Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail'])->name('verify-email');
    
    // Login
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    
    // Login OTP
    Route::get('/login/otp', [AuthController::class, 'showLoginOtp'])->name('login.otp');
    Route::post('/login/otp', [AuthController::class, 'verifyLoginOtp'])->name('login.otp.verify');
    Route::post('/login/resend-otp', [AuthController::class, 'resendLoginOtp'])->name('login.resend-otp');
});

// Logout (requires auth)
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Catch-all for SPA-style routing (keep application routes)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api|register|login|verify-email|logout).*$');
