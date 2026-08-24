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
use App\Support\MarkdownRenderer;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
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

        // Register Blade directives for Markdown rendering. @markdown emits
        // block HTML; @markdownInline drops the wrapping <p> for the spots the
        // text lands inside a <label> or a heading, where blocks are invalid.
        Blade::directive('markdown', fn ($expr) => "<?php echo \\App\\Support\\MarkdownRenderer::render($expr); ?>");
        Blade::directive('markdownInline', fn ($expr) => "<?php echo \\App\\Support\\MarkdownRenderer::renderInline($expr); ?>");
    }
}
