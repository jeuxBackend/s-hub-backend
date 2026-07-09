<?php

namespace App\Http\Controllers\Api\Institute;

use App\Actions\Institute\UpdateInstitueAction;
use App\Actions\User\GetUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\UpdateInstituteRequest;
use App\Http\Resources\UserResource;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InstituteController extends Controller
{
    /**
     * Update institute data
     */
    public function update(UpdateInstituteRequest $request, UpdateInstitueAction $action, GetUserAction $getUserAction)
    {
        try {
            $user = Auth::user();
            $institute = $user->institution;

            if (!$institute) {
                return $this->errorResponse("Institution not found for this user", 404);
            }

            $action->handle($institute, $request->validated());

            return $this->successResponse(
                $this->buildPrincipalResponse($user->id, $getUserAction),
                "Institution updated successfully",
                200
            );
        } catch (\Throwable $th) {
            return $this->exceptionResponse($th);
        }
    }

    public function updateSlogan(Request $request, UpdateInstitueAction $action, GetUserAction $getUserAction)
    {
        try {
            $validated = $request->validate([
                'slogan' => ['nullable', 'string', 'max:255'],
                'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            ]);

            $user = Auth::user();
            $institute = $user?->institution;

            if (!$institute) {
                return $this->errorResponse('Institution not found for this user', 404);
            }

            $payload = [];

            if (array_key_exists('slogan', $validated)) {
                $payload['slogan'] = $validated['slogan'];
            }

            if ($request->hasFile('logo') && array_key_exists('logo', $validated)) {
                $payload['logo'] = $validated['logo'];
            }

            $action->handle($institute, $payload);

            return $this->successResponse(
                $this->buildPrincipalResponse($user->id, $getUserAction),
                'Institution updated successfully',
                200
            );
        } catch (\Throwable $th) {
            return $this->exceptionResponse($th);
        }
    }

    private function buildPrincipalResponse(int $userId, GetUserAction $getUserAction): array
    {
        $user = $getUserAction->handle($userId);
        $unreadNotificationCount = NotificationLog::where('user_id', $userId)
            ->where('is_read', false)
            ->count();

        return [
            'user' => new UserResource($user),
            'role' => $user->role->value,
            'is_registered' => !is_null($user->password),
            'unread_notification_count' => $unreadNotificationCount,
        ];
    }
}
