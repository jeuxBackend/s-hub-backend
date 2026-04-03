<?php

namespace App\Http\Controllers\Api\Grade;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grade\StoreGradeRequest;
use App\Http\Requests\Grade\UpdateGradeRequest;
use App\Http\Requests\Grade\GetGradeRequest;
use App\Actions\Grade\StoreGradeAction;
use App\Actions\Grade\UpdateGradeAction;
use App\Actions\Grade\GetGradeAction;
use App\Http\Resources\StudentGradeResource;
use App\Models\StudentGrade;
// use App\Traits\ResponsesTrait;

class GradeController extends Controller
{
    // use ResponsesTrait;

    public function index(GetGradeRequest $request, GetGradeAction $action)
    {
        $grades = $action->handle($request->validated());

        return $this->successResponse(StudentGradeResource::collection($grades));
    }

public function store(StoreGradeRequest $request, StoreGradeAction $action)
{
    $grades = $action->handle($request->validated());

    return $this->successResponse(
        StudentGradeResource::collection(collect($grades)),
        'Grades recorded successfully.'
    );
}

public function update(UpdateGradeRequest $request, UpdateGradeAction $action, $classroom, $grade)
{
    $gradeModel = StudentGrade::where('id', $grade)
        ->where('classroom_id', $classroom)
        ->firstOrFail();

    $updatedGrade = $action->handle($gradeModel, $request->validated());

    return $this->successResponse(new StudentGradeResource($updatedGrade), 'Grade updated successfully.');
}

}
