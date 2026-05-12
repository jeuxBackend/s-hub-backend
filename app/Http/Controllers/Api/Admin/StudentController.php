<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalStudentsAction;
use App\Actions\Admin\CreateGlobalStudentAction;
use App\Actions\Admin\UpdateGlobalStudentAction;
use App\Actions\Admin\DeleteGlobalStudentAction;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        protected GetGlobalStudentsAction $getGlobalStudentsAction,
        protected CreateGlobalStudentAction $createGlobalStudentAction,
        protected UpdateGlobalStudentAction $updateGlobalStudentAction,
        protected DeleteGlobalStudentAction $deleteGlobalStudentAction
    ) {}

    public function index(Request $request)
    {
        $students = $this->getGlobalStudentsAction->handle($request->all());
        return $this->successResponse($students, 'Global students list retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'student_phone_number' => 'nullable|string|unique:students,student_phone_number',
            'gender' => 'required|string',
            'term' => 'required|string',
            'classroom_id' => 'required|exists:classrooms,id',
            'institution_id' => 'required|exists:institutions,id',
            'guardian_id' => 'required|exists:users,id',
            'age' => 'nullable|integer',
            'religion' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $student = $this->createGlobalStudentAction->handle($data);
        return $this->successResponse($student, 'Student created successfully', 201);
    }

    public function show($id)
    {
        $student = Student::with(['institution', 'classroom', 'guardian'])->findOrFail($id);
        return $this->successResponse($student, 'Student retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sur_name' => 'sometimes|string|max:255',
            'student_phone_number' => 'nullable|string|unique:students,student_phone_number,' . $id,
            'gender' => 'sometimes|string',
            'term' => 'sometimes|string',
            'classroom_id' => 'sometimes|exists:classrooms,id',
            'institution_id' => 'sometimes|exists:institutions,id',
            'guardian_id' => 'sometimes|exists:users,id',
            'age' => 'nullable|integer',
            'religion' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $student = $this->updateGlobalStudentAction->handle($data, $id);
        return $this->successResponse($student, 'Student updated successfully');
    }

    public function destroy($id)
    {
        $this->deleteGlobalStudentAction->handle($id);
        return $this->successResponse(null, 'Student deleted successfully');
    }
}
