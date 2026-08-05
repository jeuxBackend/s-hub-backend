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

    public function index(GetGradeRequest $request, $classroom)
    {
        $filters = $request->validated();
        $filters['classroom_id'] = $classroom;

        // Ensure teacher can only view if authorized
        $user = auth()->user();
        if ($user->isRole(\App\Enums\UserRole::Teacher)) {
            // Checked by GetGradeRequest authorization already, but just to be safe:
            // The request authorization handles checking if teacher is assigned to subject or classroom.
        }

        $students = \App\Models\Student::with('guardian')->where('classroom_id', $classroom)->where('status', true)->get();

        $gradesQuery = \App\Models\StudentGrade::with('subject', 'recordedBy')->where('classroom_id', $classroom);
        
        if (!empty($filters['subject_id'])) {
            $gradesQuery->where('subject_id', $filters['subject_id']);
        }
        if (!empty($filters['term'])) {
            $gradesQuery->where(function($q) use ($filters) {
                $q->where('term', $filters['term'])
                  ->orWhere('type', 'years_marks');
            });
        }
        if (!empty($filters['date'])) {
            $gradesQuery->whereDate('date', $filters['date']);
        }

        $allGrades = $gradesQuery->get();

        $result = $students->map(function ($student) use ($allGrades) {
            $studentGrades = $allGrades->where('student_id', $student->id);
            
            // Group grades by type dynamically
            $gradesByType = $studentGrades->groupBy('type')->map(function ($grades) {
                return $grades->first(); // Take the first grade of each type
            })->toArray();
            
            // Predefined grade types for backward compatibility
            $predefinedGrades = [
                'test_1' => $studentGrades->where('type', 'test_1')->first(),
                'test_2' => $studentGrades->where('type', 'test_2')->first(),
                'test_3' => $studentGrades->where('type', 'test_3')->first(),
                'test_4' => $studentGrades->where('type', 'test_4')->first(),
                'final_marks' => $studentGrades->where('type', 'final_marks')->first(),
                'exam_marks' => $studentGrades->where('type', 'exam_marks')->first(),
                'years_marks' => $studentGrades->where('type', 'years_marks')->first(),
                // 'exam' => $studentGrades->where('type', 'exam')->first(),
                // 'assignment' => $studentGrades->where('type', 'assignment')->first(),
                // 'quiz' => $studentGrades->where('type', 'quiz')->first(),
            ];
            
            // Merge predefined grades with dynamic grades
            $allGradesArray = array_merge($predefinedGrades, $gradesByType);
            
            return [
                'student_id' => $student->id,
                'student_name' => trim($student->first_name . ' ' . $student->last_name . ' ' . $student->sur_name),
                'profile_picture' => $student->profile_picture,
                'registration_number' => $student->registration_number,
                'grades' => $allGradesArray
            ];
        });

        return $this->successResponse($result, 'Grades retrieved successfully.');
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