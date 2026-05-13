<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\User\ToggleUserStatusAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function index(Request $request, \App\Actions\Admin\GetGlobalTeachersAction $action)
    {
        $data = $request->all();
        $data['manager_id'] = auth()->id();

        $teachers = $action->handle($data);
        return $this->successResponse($teachers, 'Teachers retrieved successfully');
    }

    public function show($id)
    {
        $teacher = User::with('institution')->findOrFail($id);
        return $this->successResponse($teacher, 'Teacher retrieved successfully');
    }

    public function update($id, ToggleUserStatusAction $action)
    {
        $teacher = $action->handle($id);
        return $this->successResponse($teacher, 'Teacher updated successfully');
    }
}
