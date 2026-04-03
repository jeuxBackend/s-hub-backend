<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Actions\Auth\LoginAction;
use App\Http\Requests\LoginRequest;
use Throwable;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, LoginAction $loginAction)
    {
        try {
            $result = $loginAction->handle($request->validated());

            return $this->successResponse($result, 'Login successful');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
