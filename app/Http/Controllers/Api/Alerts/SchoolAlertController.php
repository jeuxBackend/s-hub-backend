<?php

namespace App\Http\Controllers\Api\Alerts;

use App\Http\Controllers\Controller;
use App\Models\SchoolAlert;
use App\Services\SchoolAlertService;
use Illuminate\Http\Request;
use Throwable;

class SchoolAlertController extends Controller
{
    public function index(Request $request, SchoolAlertService $service)
    {
        try {
            return $this->successResponse(
                $service->listActiveAlertsForUser(auth()->user()),
                'Alerts retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function current(Request $request, SchoolAlertService $service)
    {
        return $this->index($request, $service);
    }

    public function triggerAbduction(Request $request, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['teacher', 'school-admin']);

            $data = $request->validate([
                'title' => 'nullable|string|max:255',
                'message' => 'nullable|string',
                'meta' => 'nullable|array',
            ]);

            $alert = $service->triggerAbduction(auth()->user(), $data);

            return $this->successResponse($alert, 'Potential abduction alert raised successfully.', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function confirmAbduction(SchoolAlert $alert, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['teacher', 'school-admin']);

            $updated = $service->confirmAbduction($alert, auth()->user());

            return $this->successResponse($updated, 'Abduction alert confirmed successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function triggerEmergency(Request $request, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['principal', 'school-admin']);

            $data = $request->validate([
                'title' => 'nullable|string|max:255',
                'message' => 'nullable|string',
                'meta' => 'nullable|array',
            ]);

            $alert = $service->triggerEmergency(auth()->user(), $data);

            return $this->successResponse($alert, 'Emergency alert raised successfully.', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function respond(Request $request, SchoolAlert $alert, SchoolAlertService $service)
    {
        try {
            $user = auth()->user();
            $this->authorizeRole(['teacher', 'school-admin', 'principal', 'parent']);

            $data = $request->validate([
                'student_id' => 'nullable|exists:students,id',
                'response_type' => 'required|string|max:50',
                'note' => 'nullable|string|max:2000',
                'meta' => 'nullable|array',
            ]);

            $response = $service->recordResponse($alert, $user, $data);

            return $this->successResponse($response, 'Alert response saved successfully.', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function resolve(Request $request, SchoolAlert $alert, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['principal', 'school-admin']);

            $data = $request->validate([
                'meta' => 'nullable|array',
            ]);

            $updated = $service->resolveAlert($alert, auth()->user(), $data);

            return $this->successResponse($updated, 'Alert resolved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    protected function authorizeRole(array $roles): void
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        if (!in_array($role, $roles, true)) {
            abort(403, 'Unauthorized.');
        }
    }
}
