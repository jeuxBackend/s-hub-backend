<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdminRole;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\GeneralReport;
use App\Models\User;
use App\Http\Resources\GeneralReportResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\UserRole;

class GeneralReportController extends Controller
{
    /**
     * Which reported_to_role values this viewer's role should match.
     * Sub-admins see (and can act on) manager reports too, since those
     * are always targeted at "admin" rather than a specific sub-admin.
     */
    private function visibleRolesFor(string $role): array
    {
        if ($role === AdminRole::SubAdmin->value) {
            return [AdminRole::SubAdmin->value, AdminRole::Admin->value];
        }

        return [$role];
    }

    /**
     * Available report status values, for populating status filters/selects.
     */
    public function statuses()
    {
        return $this->successResponse(ReportStatus::options(), 'Report statuses retrieved successfully.');
    }

    /**
     * Display a listing of the reports.
     */
    public function index(Request $request)
    {
        $request->validate([
            'manager_id' => 'nullable|integer|exists:admins,id',
            'status' => ['nullable', Rule::in(ReportStatus::values())],
            'month' => 'nullable|date_format:Y-m',
            'per_page' => 'nullable|integer|min:1|max:100',
            'institution_id' => 'nullable|integer|exists:institutions,id',
            'reporter_role' => ['nullable', Rule::in([...UserRole::values(), ...AdminRole::values()])],
        ]);

        $user = auth()->user();
        $role = $user->role->value;
        $institutionId = $user->institution_id;

        $query = GeneralReport::with(['reporter', 'resolvedBy', 'institution'])
            ->where(function ($q) use ($user, $role, $institutionId) {
                // User can see reports they created
                $q->where(function ($subQ) use ($user) {
                    $subQ->where('reporter_id', $user->id)
                         ->where('reporter_type', get_class($user));
                });

                // User can see reports assigned to their role
                $q->orWhere(function ($subQ) use ($role, $institutionId) {
                    $subQ->whereIn('reported_to_role', $this->visibleRolesFor($role));

                    if ($role === UserRole::Principal->value || $role === UserRole::Teacher->value || $role === UserRole::Parent->value) {
                        $subQ->where('institution_id', $institutionId);
                    }
                    // For manager, we should ideally check all institutions they manage,
                    // but if manager manages multiple, it requires more complex logic.
                    // For simplicity, if institution_id is null, it's global.
                    // Admin and Subadmin can see all reports assigned to them.
                });
            });

        // Filter to reports created by a specific manager.
        if ($request->filled('manager_id')) {
            $query->where('reporter_id', $request->input('manager_id'))
                  ->where('reporter_type', Admin::class);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // month=YYYY-MM
        if ($request->filled('month')) {
            $month = \Carbon\Carbon::createFromFormat('Y-m', $request->input('month'));
            $query->whereYear('created_at', $month->year)
                  ->whereMonth('created_at', $month->month);
        }

        if ($request->filled('institution_id')) {
            $query->where('institution_id', $request->input('institution_id'));
        }

        if ($request->filled('reporter_role')) {
            $reporterRole = $request->input('reporter_role');

            if (in_array($reporterRole, UserRole::values(), true)) {
                $query->where('reporter_type', User::class)
                      ->whereIn('reporter_id', User::where('role', $reporterRole)->pluck('id'));
            } else {
                $query->where('reporter_type', Admin::class)
                      ->whereIn('reporter_id', Admin::where('role', $reporterRole)->pluck('id'));
            }
        }

        $query->latest();

        $reports = $query->paginate($request->input('per_page', 20));

        return $this->paginatedResponse(
            GeneralReportResource::collection($reports),
            'Reports retrieved successfully.'
        );
    }

    /**
     * Store a newly created report.
     */
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->role === AdminRole::Manager) {
            // Managers only ever report to admin (sub-admins see these too, via visibleRolesFor()).
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ]);
            $reportedToRole = AdminRole::Admin->value;
        } else {
            $allowedTargets = match ($user->role) {
                UserRole::Parent => [UserRole::Principal->value, AdminRole::Manager->value],
                UserRole::Teacher => [UserRole::Principal->value, AdminRole::Manager->value],
                UserRole::Principal => [AdminRole::Admin->value, AdminRole::Manager->value, UserRole::SchoolAdmin->value],
                UserRole::SchoolAdmin => [AdminRole::Admin->value, AdminRole::Manager->value, UserRole::Principal->value],
                AdminRole::Admin, AdminRole::SubAdmin => [AdminRole::Manager->value],
                default => []
            };

            $validated = $request->validate([
                'reported_to_role' => ['required', Rule::in($allowedTargets)],
                'title' => 'required|string|max:255',
                'description' => 'required|string',
            ]);
            $reportedToRole = $validated['reported_to_role'];
        }

        $report = GeneralReport::create([
            'reporter_id' => $user->id,
            'reporter_type' => get_class($user),
            'institution_id' => $user->institution_id ?? null,
            'reported_to_role' => $reportedToRole,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => ReportStatus::Pending->value
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
        if ($report->status !== ReportStatus::Pending->value) {
            return $this->errorResponse('Cannot update a report that is already being processed or resolved.', 403);
        }

        if ($user->role === AdminRole::Manager) {
            // Managers only ever report to admin — not editable.
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
            ]);
        } else {
            $allowedTargets = match ($user->role) {
                UserRole::Parent => [UserRole::Principal->value, AdminRole::Manager->value],
                UserRole::Teacher => [UserRole::Principal->value, AdminRole::Manager->value],
                UserRole::Principal => [AdminRole::Admin->value, AdminRole::Manager->value, UserRole::SchoolAdmin->value],
                UserRole::SchoolAdmin => [AdminRole::Admin->value, AdminRole::Manager->value, UserRole::Principal->value],
                AdminRole::Admin, AdminRole::SubAdmin => [AdminRole::Manager->value],
                default => []
            };

            $validated = $request->validate([
                'reported_to_role' => ['sometimes', Rule::in($allowedTargets)],
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
            ]);
        }

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

        $isAssignee = in_array($report->reported_to_role, $this->visibleRolesFor($user->role->value), true);

        if (!$isAssignee) {
            return $this->errorResponse('Unauthorized. Only the assigned role can resolve this report.', 403);
        }

        // Check institution logic for assignee
        if (in_array($user->role->value, [UserRole::Principal->value, UserRole::Teacher->value, UserRole::Parent->value]) && $report->institution_id !== $user->institution_id) {
            return $this->errorResponse('Unauthorized to update this report.', 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(ReportStatus::values())],
            'response' => 'nullable|string',
        ]);

        $isClosing = in_array($validated['status'], [
            ReportStatus::Resolved->value,
            ReportStatus::Rejected->value,
            ReportStatus::Closed->value,
        ], true);

        $report->update([
            'status' => $validated['status'],
            'response' => isset($validated['response']) ? $validated['response'] : $report->response,
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
        $report = GeneralReport::with(['reporter', 'resolvedBy', 'institution'])->findOrFail($id);

        $canView = ($report->reporter_id === $user->id && $report->reporter_type === get_class($user))
            || in_array($report->reported_to_role, $this->visibleRolesFor($user->role->value), true);
        if (!$canView) {
            return $this->errorResponse('Unauthorized to view this report.', 403);
        }

        return $this->successResponse(
            new GeneralReportResource($report),
            'Report retrieved successfully.'
        );
    }

    /**
     * Mark the report as read by its assignee. Idempotent — a second call
     * on an already-read report is a no-op, not an error.
     */
    public function markAsRead($id)
    {
        $user = auth()->user();
        $report = GeneralReport::findOrFail($id);

        $isAssignee = in_array($report->reported_to_role, $this->visibleRolesFor($user->role->value), true);

        if (!$isAssignee) {
            return $this->errorResponse('Unauthorized. Only the assigned role can mark this report read.', 403);
        }

        if ($report->read_at === null) {
            $report->update(['read_at' => now()]);
        }

        return $this->successResponse(
            new GeneralReportResource($report->load(['reporter', 'resolvedBy', 'institution'])),
            'Report marked as read.'
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
        $isAdmin = $user->role === AdminRole::Admin;

        if (!$isReporter && !$isAdmin) {
            return $this->errorResponse('Only the original reporter can delete this report.', 403);
        }

        $report->delete();

        return $this->successResponse(null, 'Report deleted successfully.');
    }
}