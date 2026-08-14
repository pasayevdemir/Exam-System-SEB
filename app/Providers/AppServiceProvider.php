<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Providers;

use App\Services\AdminCredentials;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton so the credential row is read once per request rather than
        // on every call — the login check, the middleware-protected pages and
        // the delete confirmation all ask the same instance.
        $this->app->singleton(AdminCredentials::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use custom pagination view
        Paginator::defaultView('pagination.bootstrap-4');
    }
}
