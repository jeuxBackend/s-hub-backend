<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalTeachersAction;
use App\Actions\Admin\CreateGlobalTeacherAction;
use App\Actions\Admin\UpdateGlobalTeacherAction;
use App\Actions\Admin\DeleteGlobalTeacherAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function __construct(
        protected GetGlobalTeachersAction $getGlobalTeachersAction,
        protected CreateGlobalTeacherAction $createGlobalTeacherAction,
        protected UpdateGlobalTeacherAction $updateGlobalTeacherAction,
        protected DeleteGlobalTeacherAction $deleteGlobalTeacherAction
    ) {
    }

    public function index(Request $request)
    {
        $data = $request->all();
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $teachers = $this->getGlobalTeachersAction->handle($data);
        return $this->paginatedResponse(
            JsonResource::collection($teachers),
            'Global teachers list retrieved successfully'
        );
    }

    public function store(Request $request)
    {
        $ids = auth()->user()->assignedInstitutionIds();

        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'sur_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'institution_id' => array_filter([
                'required',
                'exists:institutions,id',
                $ids !== null ? Rule::in($ids) : null,
            ]),
            'staff_number' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_number' => 'nullable|string|max:255',
        ]);

        $teacher = $this->createGlobalTeacherAction->handle($data);
        return $this->successResponse($teacher, 'Teacher created successfully', 201);
    }

    /**
     * Get teacher by id, with details (institution + classroom assignments).
     */
    public function show($id)
    {
        $query = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])
            ->with(['institution', 'classrooms']);
        $this->scopeToAssignedSchools($query);

        $teacher = $query->findOrFail($id);

        return $this->successResponse($teacher, 'Teacher retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $this->assertInScope($id);

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'sur_name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'nullable|string|min:8',
            'institution_id' => 'sometimes|exists:institutions,id',
            'status' => 'sometimes|boolean',
            'staff_number' => 'sometimes|nullable|string|max:255',
            'emergency_contact_name' => 'sometimes|nullable|string|max:255',
            'emergency_number' => 'sometimes|nullable|string|max:255',
        ]);

        $teacher = $this->updateGlobalTeacherAction->handle($data, $id);
        return $this->successResponse($teacher, 'Teacher updated successfully');
    }

    public function destroy($id)
    {
        $this->assertInScope($id);

        $this->deleteGlobalTeacherAction->handle($id);
        return $this->successResponse(null, 'Teacher deleted successfully');
    }

    private function scopeToAssignedSchools($query): void
    {
        $ids = auth()->user()->assignedInstitutionIds();

        if ($ids !== null) {
            $query->whereIn('institution_id', $ids);
        }
    }

    /**
     * Abort with 404 if this teacher's institution isn't one the acting
     * sub-admin is restricted to (no-op for admin, manager, or an
     * unrestricted sub-admin).
     */
    private function assertInScope($teacherId): void
    {
        $ids = auth()->user()->assignedInstitutionIds();

        if ($ids === null) {
            return;
        }

        $teacher = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])
            ->findOrFail($teacherId);

        if (!in_array($teacher->institution_id, $ids, true)) {
            abort(404);
        }
    }
}

