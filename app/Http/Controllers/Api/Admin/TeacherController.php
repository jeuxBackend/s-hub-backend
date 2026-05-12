<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalTeachersAction;
use App\Actions\Admin\CreateGlobalTeacherAction;
use App\Actions\Admin\UpdateGlobalTeacherAction;
use App\Actions\Admin\DeleteGlobalTeacherAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    public function __construct(
        protected GetGlobalTeachersAction $getGlobalTeachersAction,
        protected CreateGlobalTeacherAction $createGlobalTeacherAction,
        protected UpdateGlobalTeacherAction $updateGlobalTeacherAction,
        protected DeleteGlobalTeacherAction $deleteGlobalTeacherAction
    ) {}

    public function index(Request $request)
    {
        $teachers = $this->getGlobalTeachersAction->handle($request->all());
        return $this->successResponse($teachers, 'Global teachers list retrieved successfully');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'required|string|min:8',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        $teacher = $this->createGlobalTeacherAction->handle($data);
        return $this->successResponse($teacher, 'Teacher created successfully', 201);
    }

    public function show($id)
    {
        $teacher = User::where('role', \App\Enums\UserRole::Teacher->value)
            ->with('institution')
            ->findOrFail($id);
        return $this->successResponse($teacher, 'Teacher retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sur_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'nullable|string|min:8',
            'institution_id' => 'sometimes|exists:institutions,id',
        ]);

        $teacher = $this->updateGlobalTeacherAction->handle($data, $id);
        return $this->successResponse($teacher, 'Teacher updated successfully');
    }

    public function destroy($id)
    {
        $this->deleteGlobalTeacherAction->handle($id);
        return $this->successResponse(null, 'Teacher deleted successfully');
    }
}

