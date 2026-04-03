<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Actions\Auth\VerifyOtpAction;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Throwable;

class VerifyOtpController extends Controller
{
    public function __invoke(Request $request, VerifyOtpAction $verifyOtpAction)
    {
        try {
            $validated = $request->validate([
                'type'         => ['required', 'in:email,phone'],
                'otp_code'     => ['required', 'digits:6'],
                'email'        => ['required_if:type,email', 'email'],
                'phone_number' => ['required_if:type,phone', 'string'],
            ]);

            $user = $verifyOtpAction->handle($validated);

            $token = $user->createToken('auth_token')->plainTextToken;

            return $this->successResponse([
                'user'  => new UserResource($user),
                'token' => $token,
            ], 'OTP verified and user logged in');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
