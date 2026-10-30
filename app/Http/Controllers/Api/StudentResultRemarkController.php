<?php

namespace App\Http\Controllers\Api;

use App\Enums\TermType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentResultRemark;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class StudentResultRemarkController extends Controller
{
    private const TEACHER_FIELDS = [
        'conduct',
        'possible_attendance',
        'times_absent',
        'times_late',
        'teacher_remarks',
    ];

    private const PRINCIPAL_ONLY_FIELDS = [
        'head_teacher_remarks',
        'next_term_begins',
    ];

    public function show(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id' => ['required', 'exists:students,id'],
                'term' => ['required', Rule::in(TermType::values())],
            ]);

            $student = $this->authorizedStudent($validated['student_id']);

            if (!$student) {
                return $this->errorResponse('Unauthorized access to this student.', 403);
            }

            $remark = StudentResultRemark::where([
                'student_id' => $student->id,
                'classroom_id' => $student->classroom_id,
                'term' => $validated['term'],
            ])->first();

            return $this->successResponse(
                $this->format($student, $validated['term'], $remark),
                'Result remarks fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function upsert(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_id' => ['required', 'exists:students,id'],
                'term' => ['required', Rule::in(TermType::values())],
                'conduct' => ['nullable', 'string', 'max:255'],
                'possible_attendance' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'times_absent' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'times_late' => ['nullable', 'integer', 'min:0', 'max:65535'],
                'teacher_remarks' => ['nullable', 'string', 'max:2000'],
                'head_teacher_remarks' => ['nullable', 'string', 'max:2000'],
                'next_term_begins' => ['nullable', 'date'],
            ]);

            $user = auth()->user();
            $student = $this->authorizedStudent($validated['student_id']);

            if (!$student) {
                return $this->errorResponse('Unauthorized access to this student.', 403);
            }

            if (!$student->classroom_id) {
                return $this->errorResponse('This student is not assigned to a classroom.', 422);
            }

            $allowedFields = $user->role === UserRole::Teacher
                ? self::TEACHER_FIELDS
                : array_merge(self::TEACHER_FIELDS, self::PRINCIPAL_ONLY_FIELDS);

            if ($user->role === UserRole::Teacher) {
                $forbidden = array_intersect(self::PRINCIPAL_ONLY_FIELDS, array_keys($request->all()));

                if ($forbidden) {
                    return $this->errorResponse('Only the principal can set: ' . implode(', ', $forbidden) . '.', 403);
                }
            }

            // Only touch the fields that were sent so a teacher never clears the principal's fields.
            $data = collect($validated)
                ->only($allowedFields)
                ->filter(fn($value, $key) => $request->has($key))
                ->all();

            $remark = StudentResultRemark::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'classroom_id' => $student->classroom_id,
                    'term' => $validated['term'],
                ],
                $data + ['updated_by' => $user->id]
            );

            return $this->successResponse(
                $this->format($student, $validated['term'], $remark),
                'Result remarks saved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Principals/school admins: any student of their school.
     * Teachers: only students of a classroom they teach.
     */
    private function authorizedStudent(int|string $studentId): ?Student
    {
        $user = auth()->user();
        $student = Student::find($studentId);

        if (!$student || $student->institution_id !== $user->institution_id) {
            return null;
        }

        if ($user->role === UserRole::Teacher) {
            $teachesClass = $student->classroom_id
                && $student->classroom->teachers()->where('users.id', $user->id)->exists();

            return $teachesClass ? $student : null;
        }

        return $student;
    }

    private function format(Student $student, string $term, ?StudentResultRemark $remark): array
    {
        return [
            'student_id' => $student->id,
            'classroom_id' => $student->classroom_id,
            'term' => $term,
            'conduct' => $remark?->conduct,
            'possible_attendance' => $remark?->possible_attendance,
            'times_absent' => $remark?->times_absent,
            'times_late' => $remark?->times_late,
            'teacher_remarks' => $remark?->teacher_remarks,
            'head_teacher_remarks' => $remark?->head_teacher_remarks,
            'next_term_begins' => $remark?->next_term_begins?->toDateString(),
        ];
    }
}
