<?php

namespace App\Http\Controllers\Api\Classroom;

use App\Http\Controllers\Controller;
use App\Http\Requests\Classroom\StoreClassroomRequest;
use App\Http\Requests\Classroom\UpdateClassroomRequest;
use App\Http\Resources\ClassroomResource;
use App\Actions\Classroom\CreateClassroomAction;
use App\Actions\Classroom\UpdateClassroomAction;
use App\Actions\Classroom\DeleteClassroomAction;
use App\Actions\Classroom\ListClassroomsAction;
use App\Actions\Classroom\GetClassroomAction;
use App\Models\Classroom;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

class ClassroomController extends Controller
{
    public function index(ListClassroomsAction $listClassrooms)
    {
        try {
            $requester = auth()->user();

            $classrooms = $listClassrooms->handle($requester);

            $resource = new ResourceCollection(ClassroomResource::collection($classrooms));
            $resource->resource = $classrooms;

            return $this->paginatedResponse($resource);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetClassroomAction $getClassroom)
    {
        try {
            $requester = auth()->user();

            $classroom = $getClassroom->handle($id, $requester);

            return $this->successResponse(
                new ClassroomResource($classroom),
                'Classroom fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(StoreClassroomRequest $request, CreateClassroomAction $createClassroom)
    {
        try {
            $classroom = $createClassroom->handle($request->validated(), auth()->user());

            return $this->successResponse(
                new ClassroomResource($classroom),
                'Classroom created successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(UpdateClassroomRequest $request, Classroom $classroom, UpdateClassroomAction $updateClassroom)
    {
        try {
            if ($classroom->institution_id !== auth()->user()->institution->id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $updated = $updateClassroom->handle($classroom, $request->validated());

            return $this->successResponse(
                new ClassroomResource($updated),
                'Classroom updated successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(Classroom $classroom, DeleteClassroomAction $deleteClassroom)
    {
        try {
            if ($classroom->institution_id !== auth()->user()->institution->id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $deleteClassroom->handle($classroom);

            return $this->successResponse(null, 'Classroom deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
