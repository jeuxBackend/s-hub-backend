<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalStudentsAction;
use App\Actions\Admin\CreateGlobalStudentAction;
use App\Actions\Admin\UpdateGlobalStudentAction;
use App\Actions\Admin\DeleteGlobalStudentAction;
use App\Actions\Admin\GetStudentWithInvoicesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentWithInvoicesResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Throwable;

class StudentController extends Controller
{
    public function __construct(
        protected GetGlobalStudentsAction $getGlobalStudentsAction,
        protected CreateGlobalStudentAction $createGlobalStudentAction,
        protected UpdateGlobalStudentAction $updateGlobalStudentAction,
        protected DeleteGlobalStudentAction $deleteGlobalStudentAction
    ) {
    }

    public function index(Request $request)
    {
        $students = $this->getGlobalStudentsAction->handle($request->all());
        return $this->successResponse($students, 'Global students list retrieved successfully');
    }

    public function show($id, GetStudentWithInvoicesAction $getStudentAction)
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
