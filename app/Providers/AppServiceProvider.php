<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use App\Models\User;
use App\Models\Student;
use App\Models\Institution;
use App\Observers\UserStatusHistoryObserver;
use App\Observers\StudentStatusHistoryObserver;
use App\Observers\InstitutionStatusHistoryObserver;

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
        //
        RateLimiter::for('otp-resend', function ($request) {
        return Limit::perHour(5)->by($request->ip());
    });

        User::observe(UserStatusHistoryObserver::class);
        Student::observe(StudentStatusHistoryObserver::class);
        Institution::observe(InstitutionStatusHistoryObserver::class);
    }
}
