<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\AdminRole;

class CheckSubAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // If user is not a SubAdmin, bypass permission check
        // Assuming role could be an Enum instance or a string value
        $roleValue = $user->role instanceof AdminRole ? $user->role->value : $user->role;
        
        if ($roleValue !== AdminRole::SubAdmin->value) {
            return $next($request);
        }

        // Check if the specific permission exists in the SubAdmin's permissions array
        $permissions = $user->permissions ?? [];
        
        if (!in_array($permission, $permissions)) {
            return response()->json([
                'message' => 'Forbidden. You do not have the required permission: ' . $permission
            ], 403);
        }

        return $next($request);
    }
}
