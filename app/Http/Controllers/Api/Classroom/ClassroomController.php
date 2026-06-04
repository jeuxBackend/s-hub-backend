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
use App\Actions\Classroom\ListClassroomsWithoutFeeStudentsAction;
use App\Actions\Classroom\GetClassroomAction;
use App\Actions\Classroom\GetClassroomPerformanceStatsAction;
use App\Models\Classroom;
use App\Actions\Classroom\GetClassroomSubjectPerformanceAction;
use App\Models\StudentAttendance;
use App\Models\StudentInvoice;
use App\Models\StudentPerformance;
use App\Enums\AttendanceStatus;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

class ClassroomController extends Controller
{
    public function index(ListClassroomsAction $listClassrooms)
    {
        try {
            $requester = auth()->user();

            $classrooms = $listClassrooms->handle($requester);

            $classrooms->transform(function ($classroom) {
                // Total Students
                $classroom->students_count = $classroom->students_count ?? $classroom->students()->count();

                // Average Performance
                $classroom->average_performance = StudentPerformance::where('class_id', $classroom->id)
                    ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                    ->value('avg_perf');
                $classroom->average_performance = round($classroom->average_performance, 2);

                // Average Attendance 
                $attendance = StudentAttendance::where('classroom_id', $classroom->id)
                    ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                    ->first();
                $classroom->average_attendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

                // Tuition Counts
                $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('status', 'paid')
                    ->count();

                $classroom->owing_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('due_amount', '>', 0)
                    ->count();

                return $classroom;
            });

            return $this->successResponse(
                ClassroomResource::collection($classrooms),
                'Classrooms fetched successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function indexWithoutFeeStudents(ListClassroomsWithoutFeeStudentsAction $listClassrooms)
    {
        try {
            $requester = auth()->user();

            $classrooms = $listClassrooms->handle($requester);

            $classrooms->transform(function ($classroom) {
                // Total Students
                $classroom->students_count = $classroom->students_count ?? $classroom->students()->count();

                // Average Performance
                $classroom->average_performance = StudentPerformance::where('class_id', $classroom->id)
                    ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                    ->value('avg_perf');
                $classroom->average_performance = round($classroom->average_performance, 2);

                // Average Attendance 
                $attendance = StudentAttendance::where('classroom_id', $classroom->id)
                    ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                    ->first();
                $classroom->average_attendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

                // Tuition Counts
                $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('status', 'paid')
                    ->count();

                $classroom->owing_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('due_amount', '>', 0)
                    ->count();

                return $classroom;
            });

            return $this->successResponse(
                ClassroomResource::collection($classrooms),
                'Classrooms fetched successfully'
            );
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

    public function subjectPerformance($id, GetClassroomSubjectPerformanceAction $action)
    {
        try {
            $classroom = Classroom::findOrFail($id);

            // Ensure the requester belongs to the same institution
            if ($classroom->institution_id !== auth()->user()->institution_id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $data = $action->handle($id);

            return $this->successResponse($data, 'Classroom subject performance retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function performanceStats($id, GetClassroomPerformanceStatsAction $action)
    {
        try {
            $classroom = Classroom::findOrFail($id);
            
            // Check school admin/principal scope
            if ($classroom->institution_id !== auth()->user()->institution_id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $data = $action->handle($id);

            return $this->successResponse($data, 'Classroom performance stats retrieved successfully');
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

    public function getClassRoomsList()
    {
        try {
            $institutionId = auth()->user()->institution_id;

            $classrooms = Classroom::where('institution_id', $institutionId)
                ->withCount('students')
                ->with(['inCharge', 'subjects'])
                ->get()
                ->map(function ($classroom) {
                    // Average Performance (Percentage)
                    $classroom->average_performance = StudentPerformance::where('class_id', $classroom->id)
                        ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                        ->value('avg_perf');

                    // Average Attendance (Percentage)
                    $attendance = StudentAttendance::where('classroom_id', $classroom->id)
                        ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                        ->first();
                    $classroom->average_attendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

                    // Tuition Counts
                    $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                        ->where('status', 'paid')
                        ->count();

                    $classroom->owing_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                        ->where('due_amount', '>', 0)
                        ->count();

                    $totalSubjects = $classroom->subjects->count();
                    $uniqueTeachersCount = $classroom->subjects->pluck('teacher_id')->filter()->unique()->count();

                    return [
                        'id' => $classroom->id,
                        'name' => $classroom->name,
                        'code' => $classroom->code,
                        'in_charge' => $classroom->inCharge ? [
                            'id' => $classroom->inCharge->id,
                            'name' => $classroom->inCharge->full_name,
                        ] : null,
                        'total_subjects' => $totalSubjects,
                        'total_teachers' => $uniqueTeachersCount,
                        'total_students' => $classroom->students_count,
                        'average_performance' => round($classroom->average_performance, 2),
                        'average_attendance' => $classroom->average_attendance,
                        'paid_tuition' => $classroom->paid_tuition,
                        'owing_tuition' => $classroom->owing_tuition,
                    ];
                });

            return $this->successResponse($classrooms, 'Classrooms list with statistics retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }


}
