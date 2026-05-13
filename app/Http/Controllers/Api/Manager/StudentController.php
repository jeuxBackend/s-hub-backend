<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Student\ListStudentsAction;
use App\Actions\Student\ToggleStudentStatusAction;
use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        protected ToggleStudentStatusAction $toggleStudentStatusAction,
        protected ListStudentsAction $listStudentsAction
    ) {
    }
    public function index(Request $request)
    {
        $data = $request->all();
        $data['manager_id'] = auth()->id();

        $schools = Institution::where('manager_id', auth()->id())->pluck('id')->toArray();
        $data['school_ids'] = $schools;

        $students = $this->listStudentsAction->handle($data);
        return $this->successResponse($students, 'Students retrieved successfully');
    }

    public function toggleBlockStudent($id)
    {
        $student = $this->toggleStudentStatusAction->handle($id);
        return $this->successResponse($student, 'Student status toggled successfully');
    }
}
