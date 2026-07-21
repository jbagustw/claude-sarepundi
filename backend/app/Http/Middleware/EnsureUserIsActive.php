<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login already blocks suspended users (AuthController), but a session
 * created before a suspension shouldn't keep working — this re-checks on
 * every authenticated request so a suspension takes effect immediately.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status === 'suspended') {
            abort(403, 'Akun Anda telah disuspend. Hubungi admin platform.');
        }

        return $next($request);
    }
}
