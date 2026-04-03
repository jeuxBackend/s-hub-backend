<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Actions\Auth\ResendOtpAction;
use Illuminate\Http\Request;
use Throwable;

class ResendOtpController extends Controller
{
    public function __invoke(Request $request, ResendOtpAction $resendOtpAction)
    {
        try {
            $validated = $request->validate([
                'type'         => ['required', 'in:email,phone'],
                'email'        => ['required_if:type,email', 'email'],
                'phone_number' => ['required_if:type,phone', 'string'],
            ]);

            $result = $resendOtpAction->handle($validated);

            return $this->successResponse($result, 'OTP resent successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
