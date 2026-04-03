<?php

namespace Usama\FirebaseNotifications;

use Illuminate\Support\ServiceProvider;

class FirebaseNotificationsServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind('firebase-notifications', function () {
            return new FirebaseNotifications();
        });

        $this->mergeConfigFrom(__DIR__.'/../config/firebase-notifications.php', 'firebase-notifications');
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/firebase-notifications.php' => config_path('firebase-notifications.php'),
            ], 'firebase-notifications-config');
        }
    }
}
