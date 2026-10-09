<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Primary Today View
Route::get('/', function () {
    return Inertia::render('Today');
});

// Foundation & Architecture Verification Page
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
});
