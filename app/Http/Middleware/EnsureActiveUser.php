<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Handle an incoming request.
     *
     * Blocking an account should take effect immediately, not just at the
     * next login — this checks status on every authenticated request and
     * revokes the token being used the moment it's caught inactive.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user) {
            $isActive = $user instanceof Admin
                ? $user->status === 'active'
                : (bool) $user->status;

            if (!$isActive) {
                $user->currentAccessToken()?->delete();

                return response()->json([
                    'success' => false,
                    'message' => 'Your account has been blocked.',
                ], 403);
            }
        }

        return $next($request);
    }
}
