<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Actions\Auth\SignupAction;
use App\Http\Requests\SignupRequest;
use App\Http\Resources\UserResource;
use Throwable;

class SignupController extends Controller
{
    public function __invoke(SignupRequest $request, SignupAction $signupAction)
    {
        try {
            $result = $signupAction->handle($request->validated());

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'otp_code' => $result['otp_code'] ?? null, // only returned in local/testing
            ], 'Signup successful');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
