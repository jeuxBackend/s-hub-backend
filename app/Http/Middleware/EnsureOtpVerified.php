<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOtpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'OTP verification required.');
        }

        // Admins (admin/sub_admin/manager) have no OTP concept at all —
        // this check only applies to User-model accounts.
        if ($user instanceof Admin) {
            return $next($request);
        }

        if (! $user->otp_verified) {
            abort(403, 'OTP verification required.');
        }

        return $next($request);
    }
}
