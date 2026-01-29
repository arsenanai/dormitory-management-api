<?php

namespace App\Providers;

use App\Events\MailEventOccurred;
use App\Listeners\ProcessMailEvent;
use App\Services\UserAuthService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserAuthService::class, function ($app) {
            return new UserAuthService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(MailEventOccurred::class, ProcessMailEvent::class);

        // Log all database queries in non-production environments
        if (! app()->environment('production')) {
            \DB::listen(function ($query) {
                \Log::info('Database Query', [
                    'sql'      => $query->sql,
                    'bindings' => $query->bindings,
                    'time'     => $query->time
                ]);
            });
        }

        // Force HTTPS in production
        if (app()->environment('production')) {
            \URL::forceScheme('https');
        }
    }
}
