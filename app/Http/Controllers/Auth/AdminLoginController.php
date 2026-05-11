<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Actions\Auth\AdminLoginAction;
use App\Http\Requests\LoginRequest;
use Throwable;

class AdminLoginController extends Controller
{
    public function __invoke(LoginRequest $request, AdminLoginAction $loginAction)
    {
        try {
            $result = $loginAction->handle($request->validated());

            return $this->successResponse($result, 'Login successful');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
