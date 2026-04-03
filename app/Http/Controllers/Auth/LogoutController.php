<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        try {
            $user = $request->user();

            $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();

            return $this->successResponse(null, 'Logged out successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
