<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Configure persistent 'remember me' cookie lifetime to 24 hours (1440 minutes)
        Auth::resolved(function ($auth) {
            if (method_exists($auth->guard(), 'setRememberDuration')) {
                $auth->guard()->setRememberDuration((int) config('session.lifetime', 1440));
            }
        });
    }
}
