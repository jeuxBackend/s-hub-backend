<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Admin\CreateGlobalTeacherAction;
use App\Actions\Admin\DeleteGlobalTeacherAction;
use App\Actions\Admin\GetGlobalTeachersAction;
use App\Actions\Admin\UpdateGlobalTeacherAction;
use App\Actions\User\ToggleUserStatusAction;
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
        protected DeleteGlobalTeacherAction $deleteGlobalTeacherAction,
    ) {
    }

    public function index(Request $request)
    {
        $data = $request->all();
        $data['manager_id'] = auth()->id();

        $teachers = $this->getGlobalTeachersAction->handle($data);
        return $this->paginatedResponse(
            JsonResource::collection($teachers),
            'Teachers retrieved successfully'
        );
    }

    public function show($id)
    {
        $teacher = $this->assertInScope($id);
        $teacher->load(['institution', 'classrooms']);

        return $this->successResponse($teacher, 'Teacher retrieved successfully');
    }

    public function store(Request $request)
    {
        $institutionIds = $this->managerInstitutionIds();

        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'sur_name' => 'nullable|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'institution_id' => ['required', 'exists:institutions,id', Rule::in($institutionIds)],
            'staff_number' => 'nullable|string|max:255',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_number' => 'nullable|string|max:255',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
        }

        $teacher = $this->createGlobalTeacherAction->handle($data);
        return $this->successResponse($teacher, 'Teacher created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $this->assertInScope($id);
        $institutionIds = $this->managerInstitutionIds();

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'sur_name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'nullable|string|min:8',
            'institution_id' => ['sometimes', 'exists:institutions,id', Rule::in($institutionIds)],
            'status' => 'sometimes|boolean',
            'staff_number' => 'sometimes|nullable|string|max:255',
            'emergency_contact_name' => 'sometimes|nullable|string|max:255',
            'emergency_number' => 'sometimes|nullable|string|max:255',
            'profile_picture' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
        }

        $teacher = $this->updateGlobalTeacherAction->handle($data, $id);
        $teacher->load('institution');

        return $this->successResponse($teacher, 'Teacher updated successfully');
    }

    public function destroy($id)
    {
        $this->assertInScope($id);
        $this->deleteGlobalTeacherAction->handle($id);

        return $this->successResponse(null, 'Teacher deleted successfully');
    }

    public function toggleBlock($id, ToggleUserStatusAction $action)
    {
        $this->assertInScope($id);
        $teacher = $action->handle($id);
        $teacher->load('institution');

        return $this->successResponse($teacher, 'Teacher status toggled successfully');
    }

    private function managerInstitutionIds(): array
    {
        return auth()->user()->institutions()->pluck('id')->all();
    }

    /**
     * Abort with 404 if this teacher doesn't belong to one of the manager's
     * own schools (also blocks a manager from touching another manager's
     * teachers, which the old show()/toggleBlock() never checked).
     */
    private function assertInScope($teacherId): User
    {
        $teacher = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])
            ->whereIn('institution_id', $this->managerInstitutionIds())
            ->find($teacherId);

        if (!$teacher) {
            abort(404);
        }

        return $teacher;
    }
}
