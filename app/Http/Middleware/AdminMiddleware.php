<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if admin is logged in
        if (!session()->has('admin_logged_in')) {
            return redirect()->route('admin.login')->with('error', 'Please login to access admin panel.');
        }

        // If the user is a student (logged in via session), they should not access admin routes.
        if (session()->has('student_id')) {
            return redirect('/')->with('error', 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
