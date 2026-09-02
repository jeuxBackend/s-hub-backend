<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\GetGlobalTeachersAction;
use App\Actions\Admin\CreateGlobalTeacherAction;
use App\Actions\Admin\UpdateGlobalTeacherAction;
use App\Actions\Admin\DeleteGlobalTeacherAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherController extends Controller
{
    public function __construct(
        protected GetGlobalTeachersAction $getGlobalTeachersAction,
        protected CreateGlobalTeacherAction $createGlobalTeacherAction,
        protected UpdateGlobalTeacherAction $updateGlobalTeacherAction,
        protected DeleteGlobalTeacherAction $deleteGlobalTeacherAction
    ) {
    }

    public function index(Request $request)
    {
        $teachers = $this->getGlobalTeachersAction->handle($request->all());
        return $this->paginatedResponse(
            JsonResource::collection($teachers),
            'Global teachers list retrieved successfully'
        );
    }

    /**
     * Get teacher by id, with details (institution + classroom assignments).
     */
    public function show($id)
    {
        $teacher = User::whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])
            ->with(['institution', 'classrooms'])
            ->findOrFail($id);

        return $this->successResponse($teacher, 'Teacher retrieved successfully');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'sur_name' => 'sometimes|nullable|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'password' => 'nullable|string|min:8',
            'institution_id' => 'sometimes|exists:institutions,id',
            'status' => 'sometimes|boolean',
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

