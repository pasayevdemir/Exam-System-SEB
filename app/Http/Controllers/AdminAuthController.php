<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminLoginRequest;
use App\Http\Requests\Admin\UpdateAdminCredentialsRequest;
use App\Services\AdminCredentials;

/**
 * Getting into the admin panel, and changing the credentials that guard it.
 *
 * The only admin controller whose routes sit outside the `admin` middleware
 * group - they are what a request goes through to earn that session flag in the
 * first place.
 */
class AdminAuthController extends Controller
{
    public function __construct(private readonly AdminCredentials $credentials) {}

    public function login()
    {
        return view('admin.login');
    }

    public function authenticate(AdminLoginRequest $request)
    {

        if (! $this->credentials->verifyLogin($request->username, $request->password)) {
            return back()->with('error', 'Invalid username or password.');
        }

        $request->session()->regenerate();
        session(['admin_logged_in' => true]);

        return redirect()->route('admin.dashboard')->with('success', 'Welcome to Admin Dashboard!');
    }

    public function logout()
    {
        session()->forget('admin_logged_in');

        return redirect()->route('admin.login')->with('success', 'You have been logged out successfully.');
    }

    /**
     * The credential settings page.
     */
    public function settings()
    {
        return view('admin.settings', [
            'username' => $this->credentials->username(),
            'usingEnvFallback' => $this->credentials->isUsingEnvFallback(),
        ]);
    }

    public function updateCredentials(UpdateAdminCredentialsRequest $request)
    {
        $validated = $request->validated();

        if (! $this->credentials->passwordMatches($validated['current_password'])) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.'])
                ->withInput($request->except(['current_password', 'password', 'password_confirmation']));
        }

        $this->credentials->update($validated['username'], $validated['password'] ?? null);

        // Keep the admin signed in, but on a fresh session id.
        $request->session()->regenerate();
        session(['admin_logged_in' => true]);

        $message = filled($validated['password'] ?? null)
            ? 'Admin credentials updated. Your new password is now required everywhere, including delete confirmations.'
            : 'Admin username updated.';

        return redirect()->route('admin.settings')->with('success', $message);
    }
}
