<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\UserRole;

class CheckSchoolAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // If user is a Principal, bypass permission check (Principal has all permissions)
        if ($user->isRole(UserRole::Principal)) {
            return $next($request);
        }

        // If user is not a School Admin, bypass this specific permission check.
        // Other middlewares or policies will handle their authorization.
        if (!$user->isRole(UserRole::SchoolAdmin)) {
            return $next($request);
        }

        // Check if the specific permission exists in the School Admin's permissions array
        $permissions = $user->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required permission: ' . $permission
            ], 403);
        }

        return $next($request);
    }
}
