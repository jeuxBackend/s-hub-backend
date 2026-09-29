<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Admin\CreateGlobalStudentAction;
use App\Actions\Admin\DeleteGlobalStudentAction;
use App\Actions\Admin\UpdateGlobalStudentAction;
use App\Actions\Student\ListStudentsAction;
use App\Actions\Student\ToggleStudentStatusAction;
use App\Enums\GenderType;
use App\Enums\TermType;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Institution;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(
        protected ToggleStudentStatusAction $toggleStudentStatusAction,
        protected ListStudentsAction $listStudentsAction,
        protected CreateGlobalStudentAction $createGlobalStudentAction,
        protected UpdateGlobalStudentAction $updateGlobalStudentAction,
        protected DeleteGlobalStudentAction $deleteGlobalStudentAction,
    ) {
    }

    public function index(Request $request)
    {
        $data = $request->all();
        $data['manager_id'] = auth()->id();
        $data['school_ids'] = $this->managerInstitutionIds();

        $students = $this->listStudentsAction->handle($data);
        return $this->paginatedResponse(
            StudentResource::collection($students),
            'Students retrieved successfully'
        );
    }

    public function show($id)
    {
        $student = $this->assertInScope($id);
        $student->load(['institution', 'classroom', 'guardian']);

        return $this->successResponse($student, 'Student retrieved successfully');
    }

    public function store(Request $request)
    {
        $institutionIds = $this->managerInstitutionIds();

        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'sur_name' => 'nullable|string|max:255',
            'student_phone_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'gender' => ['required', Rule::in(GenderType::values())],
            'dob' => 'nullable|date',
            'term' => ['required', Rule::in(TermType::values())],
            'religion' => 'nullable|string|max:255',
            'nationality' => 'nullable|string|max:255',
            'country_of_birth' => 'nullable|string|max:255',
            'primary_language' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'classroom_id' => [
                'nullable',
                Rule::exists('classrooms', 'id')->where(fn ($q) => $q->whereIn('institution_id', $institutionIds)),
            ],
            'institution_id' => ['required', 'exists:institutions,id', Rule::in($institutionIds)],
            'guardian_id' => ['required', Rule::exists('users', 'id')->where('role', 'parent')],
        ]);

        $student = $this->createGlobalStudentAction->handle($data);
        return $this->successResponse($student, 'Student created successfully', 201);
    }

    public function update(Request $request, $id)
    {
        $this->assertInScope($id);
        $institutionIds = $this->managerInstitutionIds();

        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'sur_name' => 'sometimes|nullable|string|max:255',
            'student_phone_number' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|nullable|email|max:255',
            'alternate_phone' => 'sometimes|nullable|string|max:255',
            'alternate_email' => 'sometimes|nullable|email|max:255',
            'gender' => ['sometimes', Rule::in(GenderType::values())],
            'dob' => 'sometimes|nullable|date',
            'term' => ['sometimes', Rule::in(TermType::values())],
            'religion' => 'sometimes|nullable|string|max:255',
            'nationality' => 'sometimes|nullable|string|max:255',
            'country_of_birth' => 'sometimes|nullable|string|max:255',
            'primary_language' => 'sometimes|nullable|string|max:255',
            'address' => 'sometimes|nullable|string|max:255',
            'classroom_id' => [
                'sometimes',
                'nullable',
                Rule::exists('classrooms', 'id')->where(fn ($q) => $q->whereIn('institution_id', $institutionIds)),
            ],
            'institution_id' => ['sometimes', 'exists:institutions,id', Rule::in($institutionIds)],
            'guardian_id' => ['sometimes', Rule::exists('users', 'id')->where('role', 'parent')],
            'status' => 'sometimes|boolean',
        ]);

        $student = $this->updateGlobalStudentAction->handle($data, $id);
        return $this->successResponse($student, 'Student updated successfully');
    }

    public function destroy($id)
    {
        $this->assertInScope($id);
        $this->deleteGlobalStudentAction->handle($id);

        return $this->successResponse(null, 'Student deleted successfully');
    }

    public function toggleBlockStudent($id)
    {
        $this->assertInScope($id);
        $student = $this->toggleStudentStatusAction->handle($id);

        return $this->successResponse($student, 'Student status toggled successfully');
    }

    private function managerInstitutionIds(): array
    {
        return Institution::where('manager_id', auth()->id())->pluck('id')->all();
    }

    /**
     * Abort with 404 if this student doesn't belong to one of the manager's
     * own schools.
     */
    private function assertInScope($studentId): Student
    {
        $student = Student::whereIn('institution_id', $this->managerInstitutionIds())->find($studentId);

        if (!$student) {
            abort(404);
        }

        return $student;
    }
}
