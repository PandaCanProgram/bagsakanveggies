<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        Auth::shouldUse('web');

        if (Auth::guard('web')->user()?->is_admin) {
            return redirect()->route('admin.home');
        }

        return view('admin.auth.login', ['isPurchaserLogin' => false]);
    }

    public function createPurchaser(): View|RedirectResponse
    {
        Auth::shouldUse('purchaser');

        if (Auth::guard('purchaser')->user()?->is_purchaser && ! Auth::guard('purchaser')->user()->is_admin) {
            return redirect()->route('purchaser.home');
        }

        return view('admin.auth.login', ['isPurchaserLogin' => true]);
    }

    /**
     * Sign in to the account's panel while preserving the other panel's login and open forms.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $alreadySignedIn = Auth::guard('web')->check() || Auth::guard('purchaser')->check();
        $csrfToken = $request->session()->token();

        if (Auth::guard('web')->attempt([...$credentials, 'is_admin' => true], $request->boolean('remember'))) {
            $guard = 'web';
        } elseif (Auth::guard('purchaser')->attempt([...$credentials, 'is_purchaser' => true, 'is_admin' => false], $request->boolean('remember'))) {
            $guard = 'purchaser';
        } else {
            throw ValidationException::withMessages([
                'email' => 'That email and password do not match a staff account.',
            ]);
        }

        if ($alreadySignedIn && $csrfToken !== null) {
            $request->session()->put('_token', $csrfToken);
        }

        $request->session()->forget('url.intended');
        Auth::shouldUse($guard);

        return redirect()->route($guard === 'web' ? 'admin.home' : 'purchaser.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = $request->routeIs('purchaser.*') ? 'purchaser' : 'web';
        $otherGuard = $guard === 'web' ? 'purchaser' : 'web';
        Auth::guard($guard)->logout();

        if (Auth::guard($otherGuard)->check()) {
            $request->session()->migrate(true);
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route($guard === 'web' ? 'admin.login' : 'purchaser.login')->with('status', 'You’ve been signed out.');
    }
}
