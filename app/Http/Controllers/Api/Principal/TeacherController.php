<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Teacher\ListTeachersAction;
use App\Actions\Teacher\CreateTeacherAction;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\GetUserAction;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Throwable;

class TeacherController extends Controller
{
    public function index(Request $request, ListTeachersAction $action)
    {
        try {
            $teachers = $action->handle($request);
            return $this->paginatedResponse(UserResource::collection($teachers), 'Teachers retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request, CreateTeacherAction $action)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'sur_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_number' => 'required|string|unique:users,phone_number',
            'password' => 'nullable|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
            'position' => 'required|string|max:255',
            'staff_number' => 'nullable|string|max:255',
        ]);

        try {
            $requester = auth()->user();

            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            $teacher = $action->handle($data, $requester->institution_id, $requester->id);

            return $this->successResponse(new UserResource($teacher), 'Teacher created successfully', 201);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetUserAction $action)
    {
        try {
            $teacher = $action->handle($id);
            return $this->successResponse(new UserResource($teacher), 'Teacher retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, $id, UpdateUserAction $action, GetUserAction $getAction)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'sur_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'sometimes|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
            'position' => 'nullable|string|max:255',
            'staff_number' => 'nullable',
        ]);

        try {
            if ($request->hasFile('profile_picture')) {
                $data['profile_picture'] = $this->handleUserFileUpload($request, 'profile_picture', 'profile_pictures');
            }

            $teacher = $getAction->handle($id);
            $updatedTeacher = $action->handle($teacher->id, $data, auth()->user());

            return $this->successResponse(new UserResource($updatedTeacher), 'Teacher updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy($id, GetUserAction $getAction)
    {
        try {
            $teacher = $getAction->handle($id);
            $teacher->delete();

            return $this->successResponse(null, 'Teacher deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
