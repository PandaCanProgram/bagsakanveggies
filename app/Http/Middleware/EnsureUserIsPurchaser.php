<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsPurchaser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('purchaser');

        if (! $request->user()) {
            return redirect()->guest(route('purchaser.login'));
        }

        abort_unless($request->user()->is_purchaser && ! $request->user()->is_admin, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
