<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationOtpController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Guest Authentication Routes
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetOtpController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetOtpController::class, 'sendOtp'])->name('password.email');

    Route::get('/reset-password', [PasswordResetOtpController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetOtpController::class, 'reset'])->name('password.update');
});

/*
|--------------------------------------------------------------------------
| Authenticated (Unverified & Verified) Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/verify-otp', [EmailVerificationOtpController::class, 'notice'])->name('verification.notice');
    Route::post('/verify-otp', [EmailVerificationOtpController::class, 'verify'])->name('verification.verify');
    Route::post('/verify-otp/resend', [EmailVerificationOtpController::class, 'resend'])->name('verification.resend');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Protected & Verified Application Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified.otp'])->group(function () {
    Route::get('/', function () {
        return Inertia::render('Today');
    })->name('today');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/foundation', function () {
        $dbStatus = 'Connected';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'Disconnected';
        }

        return Inertia::render('Welcome', [
            'laravelVersion' => app()->version(),
            'phpVersion' => PHP_VERSION,
            'dbStatus' => $dbStatus,
        ]);
    })->name('foundation');
});
