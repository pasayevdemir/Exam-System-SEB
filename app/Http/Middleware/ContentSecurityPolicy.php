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
use Symfony\Component\HttpFoundation\Response;

/**
 * Say where a page is allowed to load things from.
 *
 * The point of the whole directive list is one line of it: script-src 'self',
 * with no 'unsafe-inline'. Question and answer text is markdown written by an
 * admin and rendered into the page, uploaded filenames are chosen by students,
 * and both end up somewhere a browser reads. Escaping is what stops those
 * becoming script; this is what stops a missed escape becoming a working one.
 *
 * It could only be turned on once the last inline <script> and the last
 * on*="..." attribute were gone, which is why it arrives at the end of the
 * refactor rather than at the start.
 */
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    private function policy(): string
    {
        $script = "'self'";
        $connect = "'self'";
        $style = "'self' 'unsafe-inline'";

        // `npm run dev` serves the modules off Vite's own origin and talks to it
        // over a websocket. Rather than exempt local from the policy - which
        // would mean the policy is first exercised in production - the dev
        // server is named while it is actually running.
        if ($devOrigin = $this->viteDevOrigin()) {
            $script .= ' '.$devOrigin;
            $style .= ' '.$devOrigin;
            $connect .= ' '.$devOrigin.' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], $devOrigin);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.$script,
            // Bootstrap's utility classes do not cover everything, so the views
            // carry style="..." attributes, which this directive also governs.
            // Unlike script, an injected style cannot run code.
            'style-src '.$style,
            "img-src 'self' data:",
            "font-src 'self'",
            'connect-src '.$connect,
            // <embed> for the PDF preview on the grading page.
            "object-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * The origin of the Vite dev server, when one is running.
     */
    private function viteDevOrigin(): ?string
    {
        $hotFile = public_path('hot');

        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) file_get_contents($hotFile));
        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
