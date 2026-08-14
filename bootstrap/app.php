<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'student' => \App\Http\Middleware\StudentMiddleware::class,
            'seb' => \App\Http\Middleware\EnsureSafeExamBrowser::class,
        ]);

        // Behind Cloudflare + the host nginx reverse-proxy, so the app must trust
        // X-Forwarded-* to know the original request was HTTPS (otherwise every
        // generated URL/redirect comes back as http://).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A form rendered before the session expired still carries the old CSRF
        // token, so submitting it lands on Laravel's bare "419 Page Expired"
        // screen with no way back. Send the user to the page they came from
        // with a readable message instead.
        // Handler::prepareException() rewrites TokenMismatchException into a 419
        // HttpException *before* render callbacks run, so match on that instead.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (!$e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }

            $message = 'Sessiyanızın vaxtı bitdi. Zəhmət olmasa yenidən cəhd edin.';

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $message], 419);
            }

            $redirect = redirect()->back()->with('error', $message);

            // Uploaded files cannot be serialised into the session, so only
            // repopulate the form when the request carried none.
            if (empty($request->allFiles())) {
                // Flashed input is written to the session store — `database`
                // driver, SESSION_ENCRYPT=false — so anything kept here lands in
                // `sessions.payload` as plaintext. Drop every credential field by
                // pattern rather than by name: an explicit denylist silently fails
                // open the moment a new *_password field is added to a form, and
                // no form ever wants a password repopulated anyway.
                $sensitive = array_filter(
                    array_keys($request->all()),
                    fn ($key) => stripos($key, 'password') !== false
                );

                $redirect->withInput($request->except([...$sensitive, '_token']));
            }

            return $redirect;
        });
    })->create();
