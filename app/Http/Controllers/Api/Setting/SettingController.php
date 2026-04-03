<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use App\Actions\Setting\GetSettingAction;
use App\Models\Setting;
use Illuminate\Http\Request;
use Throwable;

class SettingController extends Controller
{
    public function show(GetSettingAction $getSetting)
    {
        try {
            $requester = auth()->user();

            $setting = $getSetting->handle($requester);

            return $this->successResponse(
                $setting,
                'Setting fetched successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
