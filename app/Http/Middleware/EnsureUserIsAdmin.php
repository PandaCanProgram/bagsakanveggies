<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Send guests to the admin sign-in page and block signed-in users who are not admins.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('web');

        if (! $request->user()) {
            return redirect()->guest(route('admin.login'));
        }

        abort_unless((bool) $request->user()->is_admin, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
