<?php

namespace App\Http\Controllers\Api\Principal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Actions\Teacher\ListTeachersAction;
use App\Actions\Teacher\CreateTeacherAction;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\GetUserAction;
use App\Models\ClassSubjectRequirement;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Http\Requests\User\ListUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'emergency_number' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
            'position' => 'nullable|string|max:255',
            'staff_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'dob' => 'nullable|date',
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
            'emergency_number' => 'nullable|string|max:255',
            'password' => 'sometimes|string|min:8',
            'profile_picture' => 'nullable|image|max:2048',
            'position' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'dob' => 'nullable|date',
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

    public function teachingAssignments(Request $request)
    {
        try {
            $principal = auth()->user();
            $validated = $request->validate([
                'classroom_id' => ['nullable', 'integer', 'exists:classrooms,id'],
            ]);

            $classroomId = $validated['classroom_id'] ?? null;

            $requirementAssignments = ClassSubjectRequirement::query()
                ->where('institution_id', $principal->institution_id)
                ->where('is_active', true)
                ->when($classroomId !== null, fn ($query) => $query->where('classroom_id', $classroomId))
                ->with([
                    'teacher:id,first_name,sur_name,profile_picture,role,institution_id',
                    'classroom:id,name,code,institution_id',
                    'subject:id,name,code,classroom_id,institution_id',
                ])
                ->get();

            $timetableAssignments = TimetableEntry::query()
                ->where('institution_id', $principal->institution_id)
                ->when($classroomId !== null, fn ($query) => $query->where('classroom_id', $classroomId))
                ->with([
                    'teacher:id,first_name,sur_name,profile_picture,role,institution_id',
                    'classroom:id,name,code,institution_id',
                    'subject:id,name,code,classroom_id,institution_id',
                ])
                ->get();

            $assignments = $requirementAssignments
                ->concat($timetableAssignments)
                ->filter(function ($assignment) use ($principal) {
                    return $assignment->teacher instanceof User
                        && $assignment->teacher->role === UserRole::Teacher
                        && $assignment->teacher->institution_id === $principal->institution_id
                        && $assignment->classroom !== null
                        && $assignment->subject !== null;
                })
                ->unique(fn ($assignment) => $assignment->teacher_id . ':' . $assignment->classroom_id . ':' . $assignment->subject_id)
                ->sortBy([
                    ['teacher_id', 'asc'],
                    ['classroom_id', 'asc'],
                    ['subject_id', 'asc'],
                ])
                ->values();

            $teachers = $assignments
                ->groupBy('teacher_id')
                ->map(function (Collection $teacherAssignments) {
                    $teacher = $teacherAssignments->first()->teacher;

                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->full_name,
                        'first_name' => $teacher->first_name,
                        'sur_name' => $teacher->sur_name,
                        'profile_picture' => $teacher->profile_picture,
                        'classrooms' => $teacherAssignments
                            ->groupBy('classroom_id')
                            ->map(function (Collection $classroomAssignments) {
                                $classroom = $classroomAssignments->first()->classroom;

                                return [
                                    'id' => $classroom->id,
                                    'name' => $classroom->name,
                                    'code' => $classroom->code,
                                    'subjects' => $classroomAssignments
                                        ->map(function ($assignment) {
                                            return [
                                                'id' => $assignment->subject->id,
                                                'name' => $assignment->subject->name,
                                                'code' => $assignment->subject->code,
                                            ];
                                        })
                                        ->sortBy('name')
                                        ->values(),
                                ];
                            })
                            ->sortBy('name')
                            ->values(),
                    ];
                })
                ->sortBy('name')
                ->values();

            return $this->successResponse(
                $teachers,
                'Teacher teaching assignments retrieved successfully.'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
