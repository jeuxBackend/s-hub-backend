<?php

namespace App\Http\Controllers\Api\Alerts;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SchoolAlert;
use App\Http\Resources\SchoolAlertResponseResource;
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
        try {
            $user = auth()->user();

            if (($user->role?->value ?? $user->role) === 'parent') {
                return $this->successResponse(
                    $service->listParentCurrentAlerts($user),
                    'Alerts retrieved successfully.'
                );
            }

            return $this->successResponse(
                $service->listActiveAlertsForUser($user),
                'Alerts retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function responses(Request $request, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['teacher', 'school-admin', 'principal']);

            $data = $request->validate([
                'institution_id' => 'nullable|integer|exists:institutions,id',
                'alert_id' => 'nullable|integer|exists:school_alerts,id',
                'student_id' => 'nullable|integer|exists:students,id',
                'response_type' => 'nullable|string|max:50',
                'parent_response_type' => 'nullable|string|max:50',
                'school_response_type' => 'nullable|string|max:50',
                'source_role' => 'nullable|string|max:50',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $responses = $service->listResponsesForStaff(auth()->user(), $data);

            $resource = SchoolAlertResponseResource::collection($responses);
            $resource->resource = $responses;

            return $this->paginatedResponse($resource, 'Alert responses retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function parentResponses(Request $request, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['parent']);

            $data = $request->validate([
                'alert_id' => 'nullable|integer|exists:school_alerts,id',
                'student_id' => 'nullable|integer|exists:students,id',
                'parent_response_type' => 'nullable|string|max:50',
                'school_response_type' => 'nullable|string|max:50',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $responses = $service->listResponsesForParent(auth()->user(), $data);

            $resource = SchoolAlertResponseResource::collection($responses);
            $resource->resource = $responses;

            return $this->paginatedResponse($resource, 'Parent alert responses retrieved successfully.');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function triggerAbduction(Request $request, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['teacher', 'school-admin', 'principal', 'admin', 'sub_admin']);

            $data = $request->validate([
                'institution_id' => 'nullable|exists:institutions,id',
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
            $this->authorizeRole(['principal', 'admin', 'sub_admin']);

            $data = $request->validate([
                'institution_id' => 'nullable|exists:institutions,id',
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

            if (($user->role?->value ?? $user->role) === 'parent') {
                $data = $request->validate([
                    'student_ids' => 'required|array|min:1',
                    'student_ids.*' => 'required|integer|exists:students,id',
                    'response_types' => 'required|array|min:1',
                    'response_types.*' => 'required|string|max:50',
                    'note' => 'nullable|string|max:2000',
                    'meta' => 'nullable|array',
                    'notes' => 'nullable|array',
                    'notes.*' => 'nullable|string|max:2000',
                    'metas' => 'nullable|array',
                    'metas.*' => 'nullable|array',
                ]);

                $response = $service->recordParentResponses($alert, $user, $data);
            } else {
                $data = $request->validate([
                    'student_id' => 'nullable|exists:students,id',
                    'response_type' => 'required|string|max:50',
                    'note' => 'nullable|string|max:2000',
                    'meta' => 'nullable|array',
                ]);

                $response = $service->recordResponse($alert, $user, $data);
            }

            return $this->successResponse($response, 'Alert response saved successfully.', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function resolve(Request $request, SchoolAlert $alert, SchoolAlertService $service)
    {
        try {
            $this->authorizeRole(['principal', 'school-admin', 'admin', 'sub_admin']);

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

        if (auth()->user() instanceof Admin) {
            $role = auth()->user()->role?->value ?? auth()->user()->role;
        }

        if (!in_array($role, $roles, true)) {
            abort(403, 'Unauthorized.');
        }
    }
}
