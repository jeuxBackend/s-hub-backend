<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalStudentsAction;
use App\Actions\Admin\CreateGlobalStudentAction;
use App\Actions\Admin\UpdateGlobalStudentAction;
use App\Actions\Admin\DeleteGlobalStudentAction;
use App\Actions\Admin\GetStudentWithInvoicesAction;
use App\Actions\Admin\SearchGlobalStudentsAction;
use App\Enums\GenderType;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentWithInvoicesResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Throwable;

class StudentController extends Controller
{
    public function __construct(
        protected GetGlobalStudentsAction $getGlobalStudentsAction,
        protected CreateGlobalStudentAction $createGlobalStudentAction,
        protected UpdateGlobalStudentAction $updateGlobalStudentAction,
        protected DeleteGlobalStudentAction $deleteGlobalStudentAction,
        protected SearchGlobalStudentsAction $searchGlobalStudentsAction
    ) {
    }

    public function index(Request $request)
    {
        $data = $request->all();
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $students = $this->getGlobalStudentsAction->handle($data);
        return $this->successResponse($students, 'Global students list retrieved successfully');
    }

    /**
     * Flexible student search: name/registration number/phone in one field,
     * plus status, gender and school filters. Separate from index() so the
     * existing listing behaviour is untouched.
     */
    public function search(Request $request)
    {
        $data = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|boolean',
            'gender' => ['nullable', Rule::in(GenderType::values())],
            'institution_id' => 'nullable|exists:institutions,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        $data['institution_ids'] = auth()->user()->assignedInstitutionIds();

        $students = $this->searchGlobalStudentsAction->handle($data);
        return $this->paginatedResponse(JsonResource::collection($students), 'Students retrieved successfully');
    }

    public function show($id, GetStudentWithInvoicesAction $getStudentAction)
    {
        try {
            $this->assertInScope($id);

            $student = $getStudentAction->handle($id);

            return $this->successResponse(
                new StudentWithInvoicesResource($student),
                'Student with invoices retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $this->assertInScope($id);

            $data = $request->validate([
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|nullable|string|max:255',
                'sur_name' => 'sometimes|nullable|string|max:255',
                'student_phone_number' => 'sometimes|nullable|string|max:255',
                'email' => 'sometimes|nullable|email|max:255',
                'alternate_phone' => 'sometimes|nullable|string|max:255',
                'alternate_email' => 'sometimes|nullable|email|max:255',
                'classroom_id' => 'sometimes|exists:classrooms,id',
                'status' => 'sometimes|boolean',
            ]);

            $student = $this->updateGlobalStudentAction->handle($data, $id);
            return $this->successResponse($student, 'Student updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy($id)
    {
        try {
            $this->assertInScope($id);

            $this->deleteGlobalStudentAction->handle($id);
            return $this->successResponse(null, 'Student deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Abort with 404 if this student's institution isn't one the acting
     * sub-admin is restricted to (no-op for admin, manager, or an
     * unrestricted sub-admin).
     */
    private function assertInScope($studentId): void
    {
        $ids = auth()->user()->assignedInstitutionIds();

        if ($ids !== null && !in_array((int) Student::findOrFail($studentId)->institution_id, $ids, true)) {
            abort(404);
        }
    }
}
