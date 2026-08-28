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

use App\Support\Authorship;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * State the authorship of this application on every response it sends.
 *
 * The copyright headers in the source files only travel with the source. A
 * deployed instance hands out compiled Blade and bundled JavaScript, so
 * somebody looking at a running copy of this system — a browser's network
 * panel, `curl -I`, an archived HTTP capture — would otherwise see nothing
 * that says who wrote it.
 *
 * These headers close that gap, and they are the reason a running deployment
 * can be tied back to this repository long after the source was taken. They
 * are declarative and carry no user data, so they are safe to send to
 * everybody, including anonymous visitors.
 *
 * @see Authorship for the identity these headers report and for the seal that
 *      detects tampering with it.
 */
class AuthorshipHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (Authorship::headers() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
