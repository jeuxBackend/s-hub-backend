<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\GeneralReport;
use App\Http\Resources\GeneralReportResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\UserRole;

class GeneralReportController extends Controller
{
    /**
     * Display a listing of the reports.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $role = $user->role->value;
        $institutionId = $user->institution_id;

        $query = GeneralReport::with(['reporter', 'resolvedBy'])
            ->where(function ($q) use ($user, $role, $institutionId) {
                // User can see reports they created
                $q->where(function ($subQ) use ($user) {
                    $subQ->where('reporter_id', $user->id)
                         ->where('reporter_type', get_class($user));
                });

                // User can see reports assigned to their role
                $q->orWhere(function ($subQ) use ($role, $institutionId) {
                    $subQ->where('reported_to_role', $role);

                    if ($role === UserRole::Principal->value || $role === UserRole::Teacher->value || $role === UserRole::Parent->value) {
                        $subQ->where('institution_id', $institutionId);
                    }
                    // For manager, we should ideally check all institutions they manage,
                    // but if manager manages multiple, it requires more complex logic. 
                    // For simplicity, if institution_id is null, it's global.
                    // Admin and Subadmin can see all reports assigned to them.
                });
            })
            ->latest();

        return $this->successResponse(
            GeneralReportResource::collection($query->get()),
            'Reports retrieved successfully.'
        );
    }

    /**
     * Store a newly created report.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        $allowedTargets = match ($user->role) {
            UserRole::Parent => [UserRole::Principal->value, AdminRole::Manager->value],
            UserRole::Teacher => [UserRole::Principal->value, AdminRole::Manager->value],
            UserRole::Principal => [AdminRole::Admin->value, AdminRole::Manager->value],
            AdminRole::Manager => [AdminRole::Admin->value, AdminRole::SubAdmin->value],
            UserRole::SchoolAdmin => [AdminRole::Admin->value, AdminRole::Manager->value],
            default => []
        };

        $validated = $request->validate([
            'reported_to_role' => ['required', Rule::in($allowedTargets)],
            'title' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $report = GeneralReport::create([
            'reporter_id' => $user->id,
            'reporter_type' => get_class($user),
            'institution_id' => $user->institution_id ?? null,
            'reported_to_role' => $validated['reported_to_role'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => 'pending'
        ]);

        return $this->successResponse(
            new GeneralReportResource($report->load(['reporter'])),
            'Report submitted successfully.',
            201
        );
    }

    /**
     * Update the specified report.
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $report = GeneralReport::findOrFail($id);

        $isReporter = $report->reporter_id === $user->id && $report->reporter_type === get_class($user);

        if (!$isReporter) {
            return $this->errorResponse('Only the reporter can update the report details.', 403);
        }

        // Reporter can only update if it is still pending
        if ($report->status !== 'pending') {
            return $this->errorResponse('Cannot update a report that is already being processed or resolved.', 403);
        }

        $allowedTargets = match ($user->role) {
            UserRole::Parent => [UserRole::Principal->value, \App\Enums\AdminRole::Manager->value],
            UserRole::Teacher => [UserRole::Principal->value, \App\Enums\AdminRole::Manager->value],
            UserRole::Principal => [\App\Enums\AdminRole::Admin->value, \App\Enums\AdminRole::Manager->value],
            \App\Enums\AdminRole::Manager => [\App\Enums\AdminRole::Admin->value, \App\Enums\AdminRole::SubAdmin->value],
            UserRole::SchoolAdmin => [\App\Enums\AdminRole::Admin->value, \App\Enums\AdminRole::Manager->value],
            default => []
        };

        $validated = $request->validate([
            'reported_to_role' => ['sometimes', Rule::in($allowedTargets)],
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $report->update($validated);

        return $this->successResponse(
            new GeneralReportResource($report->load(['reporter', 'resolvedBy'])),
            'Report updated successfully.'
        );
    }

    /**
     * Resolve or reject the report (for upper management).
     */
    public function updateStatus(Request $request, $id)
    {
        $user = auth()->user();
        $report = GeneralReport::findOrFail($id);

        $isAssignee = $report->reported_to_role === $user->role->value;

        if (!$isAssignee) {
            return $this->errorResponse('Unauthorized. Only the assigned role can resolve this report.', 403);
        }

        // Check institution logic for assignee
        if (in_array($user->role->value, [UserRole::Principal->value, UserRole::Teacher->value, UserRole::Parent->value]) && $report->institution_id !== $user->institution_id) {
            return $this->errorResponse('Unauthorized to update this report.', 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'resolved', 'rejected', 'closed'])],
            'response' => 'nullable|string',
        ]);

        $isClosing = in_array($validated['status'], ['resolved', 'rejected', 'closed']);

        $report->update([
            'status' => $validated['status'],
            'response' => $validated['response'] ?? $report->response,
            'resolved_by_id' => $isClosing ? $user->id : $report->resolved_by_id,
            'resolved_by_type' => $isClosing ? get_class($user) : $report->resolved_by_type,
        ]);

        return $this->successResponse(
            new GeneralReportResource($report->load(['reporter', 'resolvedBy'])),
            'Report status updated successfully.'
        );
    }

    /**
     * Display the specified report.
     */
    public function show($id)
    {
        $user = auth()->user();
        $report = GeneralReport::with(['reporter', 'resolvedBy'])->findOrFail($id);

        $canView = ($report->reporter_id === $user->id && $report->reporter_type === get_class($user)) || $report->reported_to_role === $user->role->value;
        if (!$canView) {
            return $this->errorResponse('Unauthorized to view this report.', 403);
        }

        return $this->successResponse(
            new GeneralReportResource($report),
            'Report retrieved successfully.'
        );
    }

    /**
     * Remove the specified report.
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $report = GeneralReport::findOrFail($id);

        $isReporter = $report->reporter_id === $user->id && $report->reporter_type === get_class($user);

        if (!$isReporter) {
            return $this->errorResponse('Only the original reporter can delete this report.', 403);
        }

        $report->delete();

        return $this->successResponse(null, 'Report deleted successfully.');
    }
}
