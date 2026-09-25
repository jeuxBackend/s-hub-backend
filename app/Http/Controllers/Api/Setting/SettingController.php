<?php

namespace App\Http\Controllers\Api\Setting;

use App\Http\Controllers\Controller;
use App\Actions\Setting\GetSettingAction;
use App\Actions\Setting\UpsertSettingAction;
use App\Http\Resources\SettingsResource;
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
                $setting ? new SettingsResource($setting) : null,
                'Setting fetched successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Create or update about-us / privacy-policy / terms-and-conditions.
     * Admin/sub_admin only.
     */
    public function update(Request $request, UpsertSettingAction $upsertSetting)
    {
        $data = $request->validate([
            'about_us' => 'nullable|string',
            'privacy_policy' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
        ]);

        try {
            $setting = $upsertSetting->handle(auth()->user(), $data);

            return $this->successResponse(
                new SettingsResource($setting),
                'Settings saved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
