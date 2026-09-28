<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Institution\CreateSchoolAction;
use App\Actions\Institution\DeleteSchoolAction;
use App\Actions\Institution\GetSchoolsAction;
use App\Actions\Institution\UpdateSchoolAction;
use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class SchoolController extends Controller
{
    public function __construct(
        protected GetSchoolsAction $getSchoolsAction,
        protected CreateSchoolAction $createSchoolAction,
        protected UpdateSchoolAction $updateSchoolAction,
        protected DeleteSchoolAction $deleteSchoolAction
    ) {
    }

    public function index(Request $request)
    {
        $data = $request->all();
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $schools = $this->getSchoolsAction->handle($data);
        return $this->paginatedResponse(JsonResource::collection($schools), 'Schools retrieved successfully');
    }

    /**
     * Lightweight list of every school's id + name, for dropdowns etc.
     */
    public function names()
    {
        $query = Institution::select(['id', 'name']);
        $this->scopeToAssignedSchools($query);

        $schools = $query->orderBy('name')->get();
        return $this->successResponse($schools, 'School names retrieved successfully');
    }

    /**
     * Schools awaiting approval (e.g. created by a manager, which always
     * start out pending). Same filters/pagination as index(), status forced.
     */
    public function pending(Request $request)
    {
        $data = $request->all();
        $data['status'] = 'pending';
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $schools = $this->getSchoolsAction->handle($data);
        return $this->paginatedResponse(JsonResource::collection($schools), 'Pending schools retrieved successfully');
    }

    public function approve(string $id)
    {
        $this->assertInScope($id);

        $school = $this->updateSchoolAction->handle(['status' => 'approved'], $id);
        return $this->successResponse($school, 'School approved successfully');
    }

    public function reject(string $id)
    {
        $this->assertInScope($id);

        $school = $this->updateSchoolAction->handle(['status' => 'rejected'], $id);
        return $this->successResponse($school, 'School rejected successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slogan' => 'nullable|string|max:255',
            'academic_year' => 'nullable|string|max:50',
            'examination_system' => 'nullable|string|max:255',
            'physical_address' => 'nullable|string',
            'region' => 'nullable|string|max:255',

            'email' => 'required|email|unique:institutions,email',
            'alternate_email' => 'nullable|email',
            'phone_number' => 'required|string|unique:institutions,phone_number',
            'alternate_phone' => 'nullable|string|max:255',
            'telephone' => 'nullable|string|max:255',
            'email_verified' => 'sometimes|boolean',
            'phone_verified' => 'sometimes|boolean',

            'manager_id' => 'required|exists:admins,id',
            'subadmin_id' => 'nullable|exists:admins,id',
            'category_id' => 'nullable|exists:categories,id',

            'status' => 'sometimes|in:pending,approved,rejected',
            'is_blocked' => 'sometimes|boolean',

            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'timezone' => 'nullable|string|max:100',

            'subjects' => 'nullable|array',

            'alert_feature_enabled' => 'sometimes|boolean',
            'allowed_alert_types' => 'nullable|array',
            'allowed_alert_types.*' => 'in:abduction,emergency',

            'school_logo' => 'nullable|image|max:5120',
        ]);

        $school = $this->createSchoolAction->handle($data);
        return $this->successResponse($school, 'School created successfully', 201);
    }

    public function show(string $id)
    {
        $this->assertInScope($id);

        $school = Institution::with(['manager', 'category', 'principal'])
            ->withCount(['students', 'teachers', 'schoolAdmins', 'classrooms'])
            ->findOrFail($id);
        return $this->successResponse($school, 'School retrieved successfully');
    }

    /**
     * Id + name of every classroom in this school — the source list for
     * picking mock_exam_classroom_ids when editing the school.
     */
    public function classrooms(string $id)
    {
        $this->assertInScope($id);

        $classrooms = Classroom::where('institution_id', $id)
            ->select(['id', 'name'])
            ->with('subjects:id,name,classroom_id')
            ->orderBy('name')
            ->get();

        return $this->successResponse($classrooms, 'School classrooms retrieved successfully');
    }

    public function update(Request $request, string $id)
    {
        $this->assertInScope($id);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slogan' => 'sometimes|nullable|string|max:255',
            'academic_year' => 'sometimes|nullable|string|max:50',
            'examination_system' => 'sometimes|nullable|string|max:255',
            'physical_address' => 'sometimes|nullable|string',
            'region' => 'sometimes|nullable|string|max:255',

            'email' => 'sometimes|email|unique:institutions,email,' . $id,
            'alternate_email' => 'sometimes|nullable|email',
            'phone_number' => 'sometimes|string|unique:institutions,phone_number,' . $id,
            'alternate_phone' => 'sometimes|nullable|string|max:255',
            'telephone' => 'sometimes|nullable|string|max:255',
            'email_verified' => 'sometimes|boolean',
            'phone_verified' => 'sometimes|boolean',

            'manager_id' => 'sometimes|exists:admins,id',
            'subadmin_id' => 'sometimes|nullable|exists:admins,id',
            'category_id' => 'sometimes|nullable|exists:categories,id',

            'status' => 'sometimes|in:pending,approved,rejected',
            'is_blocked' => 'sometimes|boolean',

            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'timezone' => 'sometimes|nullable|string|max:100',

            'subjects' => 'sometimes|nullable|array',

            'alert_feature_enabled' => 'sometimes|boolean',
            'allowed_alert_types' => 'nullable|array',
            'allowed_alert_types.*' => 'in:abduction,emergency',
            'mock_exam_classroom_ids' => 'nullable|array',
            'mock_exam_classroom_ids.*' => [
                Rule::exists('classrooms', 'id')->where('institution_id', $id),
            ],

            'school_logo' => 'sometimes|nullable|image|max:5120',
        ]);

        $school = $this->updateSchoolAction->handle($data, $id);
        return $this->successResponse($school, 'School updated successfully');
    }

    public function toggleAlertFeature(Request $request, string $id)
    {
        $this->assertInScope($id);

        $data = $request->validate([
            'alert_feature_enabled' => 'required|boolean',
            'allowed_alert_types' => 'nullable|array',
            'allowed_alert_types.*' => 'in:abduction,emergency',
        ]);

        $school = Institution::findOrFail($id);
        $school->update([
            'alert_feature_enabled' => $data['alert_feature_enabled'],
            'allowed_alert_types' => $data['allowed_alert_types'] ?? $school->allowed_alert_types ?? ['abduction', 'emergency'],
        ]);

        return $this->successResponse($school, 'School alert feature updated successfully');
    }

    public function destroy(string $id)
    {
        $this->assertInScope($id);

        $this->deleteSchoolAction->handle($id);
        return $this->successResponse(null, 'School deleted successfully');
    }

    /**
     * Restrict a query to the acting sub-admin's assigned schools, if any.
     */
    private function scopeToAssignedSchools($query): void
    {
        $ids = auth()->user()->assignedInstitutionIds();

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }
    }

    /**
     * Abort with 404 if this institution isn't one the acting sub-admin is
     * restricted to (no-op for admin, manager, or an unrestricted sub-admin).
     */
    private function assertInScope(string $institutionId): void
    {
        $ids = auth()->user()->assignedInstitutionIds();

        if ($ids !== null && !in_array((int) $institutionId, $ids, true)) {
            abort(404);
        }
    }
}
