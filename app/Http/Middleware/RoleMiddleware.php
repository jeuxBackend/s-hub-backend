<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        $roleValue = $user && $user->role instanceof \BackedEnum ? $user->role->value : ($user ? $user->role : null);

        if (! $user || ! in_array($roleValue, $roles)) {
            abort(403, 'Unauthorized role access');
        }

        return $next($request);
    }
}
