<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'         => \App\Http\Middleware\RoleMiddleware::class,
            'otp.verified' => \App\Http\Middleware\EnsureOtpVerified::class,
            'active.user'  => \App\Http\Middleware\EnsureActiveUser::class,
            'subadmin.permission' => \App\Http\Middleware\CheckSubAdminPermission::class,
            'schooladmin.permission' => \App\Http\Middleware\CheckSchoolAdminPermission::class,
        ]);
    })
    ->withExceptions(function () {
        // ✅ Proper Exception binding (this works)
        app()->singleton(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            \App\Exceptions\Handler::class
        );
    })
    ->create();
