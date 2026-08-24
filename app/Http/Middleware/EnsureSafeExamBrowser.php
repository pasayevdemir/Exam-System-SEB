<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureSafeExamBrowser
{
    /**
     * Verify the request carries a valid SEB Config Key hash.
     *
     * seb.required is the actual kill switch: flip it
     * to false and every check below is skipped, regardless of whether
     * seb.config_key is set. The plan's original sketch only checked
     * `required` inside the "config key missing" branch, so once a real key
     * was configured for an actual exam, toggling `required` off would have
     * done nothing - the hash check would still run. Gating the whole method
     * on `required` first is what makes it an actual instant off-switch.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('seb.required')) {
            return $next($request);
        }

        $configKey = config('seb.config_key');

        if (! $configKey) {
            Log::critical('SEB required but no config key is set — exam routes blocked.');
            abort(503, 'İmtahan mühiti konfiqurasiya olunmayıb.');
        }

        $expected = strtolower(hash('sha256', $request->fullUrl().$configKey));
        $received = strtolower((string) $request->header('X-SafeExamBrowser-ConfigKeyHash'));

        if (! hash_equals($expected, $received)) {
            Log::warning('Non-SEB exam access attempt', [
                'user' => $request->user()?->id,
                'ip' => $request->ip(),
            ]);
            abort(403, 'Bu imtahana yalnız Safe Exam Browser ilə daxil olmaq olar.');
        }

        return $next($request);
    }
}
