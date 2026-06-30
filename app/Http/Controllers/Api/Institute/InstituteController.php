<?php

namespace App\Http\Controllers\Api\Institute;

use App\Http\Controllers\Controller;
use App\Http\Requests\Institute\UpdateInstituteRequest;
use App\Actions\Institute\UpdateInstitueAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstituteController extends Controller
{

    /**
     * Update institute data
     */
    public function update(UpdateInstituteRequest $request, UpdateInstitueAction $action)
    {
        try {
            $user = Auth::user();

            // Assumes institute is accessed via principal_id relationship
            $institute = $user->institution;

            if (! $institute) {
                return $this->errorResponse("Institution not found for this user", 404);
            }

            $updated = $action->handle($institute, $request->validated());

            return $this->successResponse([],"Institution updated successfully",200);
        } catch (\Throwable $th) {
            return $this->exceptionResponse($th);
        }
    }

    public function updateSlogan(Request $request, UpdateInstitueAction $action)
    {
        try {
            $validated = $request->validate([
                'slogan' => ['nullable', 'string', 'max:255'],
            ]);

            $user = Auth::user();
            $institute = $user?->institution;

            if (! $institute) {
                return $this->errorResponse('Institution not found for this user', 404);
            }

            $updated = $action->handle($institute, [
                'slogan' => $validated['slogan'] ?? null,
            ]);

            return $this->successResponse([
                'institution' => $updated,
            ], 'Institution slogan updated successfully', 200);
        } catch (\Throwable $th) {
            return $this->exceptionResponse($th);
        }
    }
}
