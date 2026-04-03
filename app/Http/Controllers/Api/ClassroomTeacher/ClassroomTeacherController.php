<?php

namespace App\Http\Controllers\Api\ClassroomTeacher;

use App\Http\Controllers\Controller;
use App\Actions\ClassroomTeacher\AllocateAction;
use App\Actions\ClassroomTeacher\UnallocateAction;
use App\Http\Requests\ClassroomTeacher\AllocateTeacherRequest;
use App\Http\Requests\ClassroomTeacher\UnallocateTeacherRequest;

class ClassroomTeacherController extends Controller
{
    public function allocate(AllocateTeacherRequest $request, AllocateAction $allocateAction)
    {
        $allocated = $allocateAction->handle($request->validated());

        if (! $allocated) {
            return $this->errorResponse('Teacher is already allocated or data is invalid');
        }

        return $this->successResponse(null, 'Teacher allocated to classroom successfully');
    }

    public function unallocate(UnallocateTeacherRequest $request, UnallocateAction $unallocateAction)
    {
        $unallocated = $unallocateAction->handle($request->validated());

        if (! $unallocated) {
            return $this->errorResponse('Teacher was not allocated to this classroom or data is invalid');
        }

        return $this->successResponse(null, 'Teacher unallocated from classroom successfully');
    }
}

