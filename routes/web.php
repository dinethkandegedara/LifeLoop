<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
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
