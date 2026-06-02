<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\StoreStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Http\Requests\Student\FilterStudentRequest;
use App\Http\Resources\StudentResource;
use App\Http\Resources\StudentWithInvoicesResource;
use App\Actions\Student\CreateStudentAction;
use App\Actions\Student\UpdateStudentAction;
use App\Actions\Student\DeleteStudentAction;
use App\Actions\Student\ListStudentsAction;
use App\Actions\Student\GetStudentAction;
use App\Actions\Admin\GetStudentWithInvoicesAction;
use App\Models\Student;
use Throwable;

class StudentController extends Controller
{
    public function index(FilterStudentRequest $request, ListStudentsAction $fetchAction)
    {

        try {
            // dd('here');
            $students = $fetchAction->handle($request->validated());

            return $this->paginatedResponse(
                StudentResource::collection($students),
                'Students fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(StoreStudentRequest $request, CreateStudentAction $createAction)
    {
        try {
            $student = $createAction->handle($request->validated());

            return $this->successResponse(
                new StudentResource($student),
                'Student created successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(UpdateStudentRequest $request, $id, UpdateStudentAction $updateAction)
    {
        try {
            $student = Student::findOrFail($id); // ✅ fetch student instance

            $updated = $updateAction->handle($request->validated(), $student); // ✅ correct order

            return $this->successResponse(
                new StudentResource($updated),
                'Student updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy($id, DeleteStudentAction $deleteAction)
    {
        try {
            $student = Student::findOrFail($id);
            $deleteAction->handle($student);

            return $this->successResponse(null, 'Student deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetStudentAction $fetchAction)
    {
        try {
            $student = $fetchAction->handle($id);

            return $this->successResponse(
                new StudentResource($student),
                'Student fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function showWithInvoices($id, GetStudentWithInvoicesAction $getStudentAction)
    {
        try {
            $student = $getStudentAction->handle($id);

            return $this->successResponse(
                new StudentWithInvoicesResource($student),
                'Student with invoices retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
