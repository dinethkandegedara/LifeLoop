<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationOtpController;
use App\Http\Controllers\Auth\PasswordResetOtpController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ScheduleOccurrenceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\WorkSessionController;
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

// Public read-only tokenized calendar subscription feed (.ics / webcal)
Route::get('/calendar/feed/{token}.ics', [\App\Http\Controllers\CalendarFeedController::class, 'feed'])
    ->middleware('throttle:60,1')
    ->name('calendar.feed');

/*
|--------------------------------------------------------------------------
| Protected & Verified Application Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified.otp'])->group(function () {
    Route::get('/', TodayController::class)->name('today');
    Route::get('/week', function (\Illuminate\Http\Request $request, \App\Services\Scheduling\ScheduleManager $manager) {
        $request->merge(['view' => 'week']);
        return app(TodayController::class)($request, $manager);
    })->name('calendar.week');
    Route::get('/month', function (\Illuminate\Http\Request $request, \App\Services\Scheduling\ScheduleManager $manager) {
        $request->merge(['view' => 'month']);
        return app(TodayController::class)($request, $manager);
    })->name('calendar.month');

    // Reporting
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');
    Route::get('/tasks/{task}/report', [\App\Http\Controllers\ReportController::class, 'taskReport'])->name('tasks.report');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/email/send-otp', [SettingsController::class, 'sendEmailOtp'])->name('settings.email.send-otp');
    Route::put('/settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email.update');
    Route::post('/settings/password/send-otp', [SettingsController::class, 'sendPasswordOtp'])->name('settings.password.send-otp');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/calendar-feed/regenerate', [\App\Http\Controllers\CalendarFeedController::class, 'regenerate'])->name('settings.calendar-feed.regenerate');

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
    Route::patch('/tasks/{task}/unarchive', [TaskController::class, 'unarchive'])->name('tasks.unarchive');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    // Recurring Schedules
    Route::post('/tasks/{task}/schedules/sync', [ScheduleController::class, 'sync'])->name('schedules.sync');
    Route::post('/tasks/{task}/schedules', [ScheduleController::class, 'store'])->name('schedules.store');
    Route::put('/schedules/{schedule}', [ScheduleController::class, 'update'])->name('schedules.update');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');

    // Schedule Occurrences
    Route::get('/occurrences', [ScheduleOccurrenceController::class, 'index'])->name('occurrences.index');
    Route::put('/occurrences/{occurrence}', [ScheduleOccurrenceController::class, 'update'])->name('occurrences.update');
    Route::patch('/occurrences/{occurrence}/complete', [ScheduleOccurrenceController::class, 'complete'])->name('occurrences.complete');
    Route::patch('/occurrences/{occurrence}/reopen', [ScheduleOccurrenceController::class, 'reopen'])->name('occurrences.reopen');
    Route::patch('/occurrences/{occurrence}/skip', [ScheduleOccurrenceController::class, 'skip'])->name('occurrences.skip');

    // Work Sessions
    Route::get('/work-sessions', [WorkSessionController::class, 'index'])->name('work-sessions.index');
    Route::post('/work-sessions', [WorkSessionController::class, 'store'])->name('work-sessions.store');
    Route::put('/work-sessions/{workSession}', [WorkSessionController::class, 'update'])->name('work-sessions.update');
    Route::delete('/work-sessions/{workSession}', [WorkSessionController::class, 'destroy'])->name('work-sessions.destroy');

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
