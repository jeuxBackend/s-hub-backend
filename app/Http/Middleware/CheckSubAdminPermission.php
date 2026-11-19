<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Enums\AdminRole;

class CheckSubAdminPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // If user is not a SubAdmin, bypass permission check
        // Assuming role could be an Enum instance or a string value
        $roleValue = $user->role instanceof AdminRole ? $user->role->value : $user->role;

        if (!in_array($roleValue, [AdminRole::SubAdmin->value, AdminRole::SubSubAdmin->value], true)) {
            return $next($request);
        }

        // Allowed if the sub-admin holds ANY one of the listed permissions.
        // For a sub-sub-admin this is limited to what its sub-admin still holds.
        $userPermissions = $user->effectivePermissions();
        $hasAny = collect($permissions)->contains(fn($permission) => in_array($permission, $userPermissions));

        if (!$hasAny) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. You do not have the required permission: ' . implode(' or ', $permissions),
            ], 403);
        }

        return $next($request);
    }
}
