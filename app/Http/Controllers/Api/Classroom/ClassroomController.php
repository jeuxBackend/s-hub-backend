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

                // Total Amount Paid and Owed
                // Total amount paid = sum of all paid invoices
                $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('status', 'paid')
                    ->sum('amount'); // Sum of paid amounts

                // Total amount owed = sum of latest due amounts for unique students
                $studentsInClassroom = $classroom->students()->pluck('id');
                
                $latestDueAmounts = [];
                foreach ($studentsInClassroom as $studentId) {
                    // Get the latest invoice for each student
                    $latestInvoice = StudentInvoice::where('student_id', $studentId)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($latestInvoice && $latestInvoice->due_amount > 0) {
                        $latestDueAmounts[] = $latestInvoice->due_amount;
                    }
                }
                
                $classroom->owing_tuition = array_sum($latestDueAmounts);

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

                // Total Amount Paid and Owed
                // Total amount paid = sum of all paid invoices
                $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                    ->where('status', 'paid')
                    ->sum('amount'); // Sum of paid amounts

                // Total amount owed = sum of latest due amounts for unique students
                $studentsInClassroom = $classroom->students()->pluck('id');
                
                $latestDueAmounts = [];
                foreach ($studentsInClassroom as $studentId) {
                    // Get the latest invoice for each student
                    $latestInvoice = StudentInvoice::where('student_id', $studentId)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($latestInvoice && $latestInvoice->due_amount > 0) {
                        $latestDueAmounts[] = $latestInvoice->due_amount;
                    }
                }
                
                $classroom->owing_tuition = array_sum($latestDueAmounts);

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

                    // Total Amount Paid and Owed
                    // Total amount paid = sum of all paid invoices
                    $classroom->paid_tuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                        ->where('status', 'paid')
                        ->sum('amount'); // Sum of paid amounts

                    // Total amount owed = sum of latest due amounts for unique students
                    $studentsInClassroom = $classroom->students()->pluck('id');
                    
                    $latestDueAmounts = [];
                    foreach ($studentsInClassroom as $studentId) {
                        // Get the latest invoice for each student
                        $latestInvoice = StudentInvoice::where('student_id', $studentId)
                            ->orderBy('created_at', 'desc')
                            ->first();
                        
                        if ($latestInvoice && $latestInvoice->due_amount > 0) {
                            $latestDueAmounts[] = $latestInvoice->due_amount;
                        }
                    }
                    
                    $classroom->owing_tuition = array_sum($latestDueAmounts);

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

    public function getAverageAttendance($id)
    {
        try {
            $classroom = Classroom::findOrFail($id);

            // Check school admin/principal scope
            if ($classroom->institution_id !== auth()->user()->institution_id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $students = $classroom->students;

            // Calculate overall average attendance for the class
            $attendance = StudentAttendance::where('classroom_id', $classroom->id)
                ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                ->first();

            $overallAverageAttendance = ($attendance->total > 0) ? round(($attendance->present / $attendance->total * 100), 2) : 0;

            // Get attendance by day of the week for each student
            $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']; // Only weekdays
            $attendanceByStudent = [];

            foreach ($students as $student) {
                $studentAttendanceData = [];

                foreach ($daysOfWeek as $index => $day) {
                    // Using DAYOFWEEK in MySQL (1=Sunday, 2=Monday, ..., 7=Saturday)
                    // Adjusting to match our array where Monday=0, Tuesday=1, etc.
                    $dayNumber = $index + 2; // Monday is 2 in DAYOFWEEK, Tuesday is 3, etc.

                    $dayAttendance = StudentAttendance::where('student_id', $student->id)
                        ->whereRaw('DAYOFWEEK(created_at) = ?', [$dayNumber])
                        ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                        ->first();

                    $dayPercentage = ($dayAttendance->total > 0) ? round(($dayAttendance->present / $dayAttendance->total * 100), 2) : 0;

                    $studentAttendanceData[] = $dayPercentage;
                }

                // Calculate overall attendance for this student
                $studentTotalAttendance = StudentAttendance::where('student_id', $student->id)
                    ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                    ->first();

                $studentOverallAttendance = ($studentTotalAttendance->total > 0) ? round(($studentTotalAttendance->present / $studentTotalAttendance->total * 100), 2) : 0;

                $attendanceByStudent[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'profile_picture' => $student->profile_picture,
                    'student_registration_number' => $student->registration_number,
                    'overall_attendance' => $studentOverallAttendance,
                    'attendance_by_day' => array_combine($daysOfWeek, $studentAttendanceData)
                ];
            }

            // Prepare chart data for class-wise average (Y-axis range 0% to 100%)
            $overallDailyAttendance = [];
            foreach ($daysOfWeek as $index => $day) {
                $dayNumber = $index + 2; // Monday is 2 in DAYOFWEEK, Tuesday is 3, etc.

                $dayAttendance = StudentAttendance::where('classroom_id', $classroom->id)
                    ->whereRaw('DAYOFWEEK(created_at) = ?', [$dayNumber])
                    ->selectRaw('count(*) as total, count(CASE WHEN status = ? THEN 1 END) as present', [AttendanceStatus::Present->value])
                    ->first();

                $dayPercentage = ($dayAttendance->total > 0) ? round(($dayAttendance->present / $dayAttendance->total * 100), 2) : 0;

                $overallDailyAttendance[] = $dayPercentage;
            }

            $chartData = [
                'labels' => $daysOfWeek,
                'datasets' => [
                    [
                        'label' => 'Class Average Attendance (%)',
                        'data' => $overallDailyAttendance,
                    ]
                ]
            ];

            return $this->successResponse([
                'classroom_id' => $classroom->id,
                'classroom_name' => $classroom->name,
                'overall_average_attendance' => $overallAverageAttendance,
                'total_students' => $students->count(), // Total number of students in class
                'present_count' => $attendance->present,
                'chart_data' => $chartData,
                'table_data' => $attendanceByStudent, // Student-level data for table display
                'days_of_week' => $daysOfWeek
            ], 'Average attendance retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getAveragePerformance($id)
    {
        try {
            $classroom = Classroom::findOrFail($id);

            // Check school admin/principal scope
            if ($classroom->institution_id !== auth()->user()->institution_id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $students = $classroom->students;

            // Get all subjects assigned to this classroom (not just those with performance data)
            $classroomSubjects = $classroom->subjects; // Assuming there's a relationship defined
            $subjects = [];
            foreach ($classroomSubjects as $subject) {
                $subjects[] = $subject->name;
            }

            // Calculate overall average performance for the class
            $overallAveragePerformance = StudentPerformance::where('class_id', $classroom->id)
                ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                ->value('avg_perf');

            $overallAveragePerformance = round($overallAveragePerformance, 2);

            // Get performance data for each student
            $performanceByStudent = [];

            // Calculate performance for each student
            foreach ($students as $student) {
                $studentPerformances = StudentPerformance::where('student_id', $student->id)
                    ->where('class_id', $classroom->id)
                    ->join('subjects', 'student_performances.subject_id', '=', 'subjects.id')
                    ->select('student_performances.*', 'subjects.name as subject_name', 'subjects.id as subject_id')
                    ->get();

                $studentPerformanceData = [];
                $subjectScores = [];

                // Initialize all subjects with 0, then fill in actual scores
                foreach ($classroomSubjects as $subject) {
                    $perf = $studentPerformances->firstWhere('subject_id', $subject->id);
                    if ($perf && $perf->total_mark > 0) {
                        $score = round(($perf->obtained_mark / $perf->total_mark * 100), 2);
                        $studentPerformanceData[$subject->name] = $score;
                        $subjectScores[] = $score;
                    } else {
                        $studentPerformanceData[$subject->name] = 0;
                        $subjectScores[] = 0;
                    }
                }

                // Calculate student's overall performance across all subjects
                $studentOverallPerformance = 0;
                if (!empty($subjectScores)) {
                    $studentOverallPerformance = round(array_sum($subjectScores) / count($subjectScores), 2);
                }

                $performanceByStudent[] = [
                    'student_id' => $student->id,
                    'student_registration_number' => $student->registration_number,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'profile_picture' => $student->profile_picture,
                    'overall_performance' => $studentOverallPerformance,
                    'performance_by_subject' => $studentPerformanceData
                ];
            }

            // Calculate average performance by subject for the class (for chart)
            $subjectAverages = [];
            foreach ($classroomSubjects as $subject) {
                $avg = StudentPerformance::where('class_id', $classroom->id)
                    ->where('subject_id', $subject->id)
                    ->selectRaw('COALESCE(AVG(CASE WHEN total_mark > 0 THEN (obtained_mark / total_mark * 100) ELSE 0 END), 0) as avg_perf')
                    ->value('avg_perf');

                $subjectAverages[] = round($avg, 2);
            }

            // Prepare chart data for class-wise average (Y-axis range 0% to 100%)
            $chartData = [
                'labels' => $subjects,
                'datasets' => [
                    [
                        'label' => 'Class Average Performance (%)',
                        'data' => $subjectAverages,
                    ]
                ]
            ];

            return $this->successResponse([
                'classroom_id' => $classroom->id,
                'classroom_name' => $classroom->name,
                'overall_average_performance' => $overallAveragePerformance,
                'total_students' => $students->count(),
                'chart_data' => $chartData,
                'table_data' => $performanceByStudent, // Student-level data for table display
                'subjects' => $subjects
            ], 'Average performance retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function getTuitionPaidOwed($id)
    {
        try {
            $classroom = Classroom::findOrFail($id);

            // Check school admin/principal scope
            if ($classroom->institution_id !== auth()->user()->institution_id) {
                abort(403, 'Unauthorized access to this classroom.');
            }

            $students = $classroom->students;

            // Calculate overall tuition statistics for the class
            $paidTuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                ->where('status', 'paid')
                ->count();

            $owingTuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                ->where('due_amount', '>', 0)
                ->count();

            $totalTuition = StudentInvoice::whereHas('student', fn($q) => $q->where('classroom_id', $classroom->id))
                ->count();

            // Get latest invoice due amount for each student
            $tuitionByStudent = [];
            $latestDueAmounts = []; // For calculating class average

            foreach ($students as $student) {
                // Get the latest invoice for the student
                $latestInvoice = StudentInvoice::where('student_id', $student->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $totalInvoices = StudentInvoice::where('student_id', $student->id)->count();
                $paidInvoices = StudentInvoice::where('student_id', $student->id)
                    ->where('status', 'paid')
                    ->count();
                $totalAmount = StudentInvoice::where('student_id', $student->id)->sum('amount');
                $paidAmount = StudentInvoice::where('student_id', $student->id)
                    ->where('status', 'paid')
                    ->sum('amount');

                $latestDueAmount = $latestInvoice ? $latestInvoice->due_amount : 0;

                // Only add to average calculation if there's an invoice
                if ($latestInvoice) {
                    $latestDueAmounts[] = $latestDueAmount;
                }

                $tuitionByStudent[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->first_name . ' ' . $student->last_name,
                    'profile_picture' => $student->profile_picture,
                    'student_registration_number' => $student->registration_number,
                    'paid_invoices' => $paidInvoices,
                    'total_invoices' => $totalInvoices,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'latest_due_amount' => $latestDueAmount, // Latest invoice due amount for this student
                    'latest_invoice_date' => $latestInvoice ? $latestInvoice->created_at : null
                ];
            }

            // Calculate class average of latest due amounts
            $classAverageLatestDue = !empty($latestDueAmounts) ? round(array_sum($latestDueAmounts) / count($latestDueAmounts), 2) : 0;

            // Prepare chart data for class-wise average (using latest due amounts)
            $chartData = [
                'labels' => ['Average Latest Due Amount'],
                'datasets' => [
                    [
                        'label' => 'Class Average Latest Due Amount',
                        'data' => [$classAverageLatestDue],
                    ]
                ]
            ];

            return $this->successResponse([
                'classroom_id' => $classroom->id,
                'classroom_name' => $classroom->name,
                'total_tuition' => $totalTuition,
                'paid_tuition' => $paidTuition,
                'owing_tuition' => $owingTuition,
                'percentage_paid' => $totalTuition > 0 ? round(($paidTuition / $totalTuition) * 100, 2) : 0,
                'percentage_owing' => $totalTuition > 0 ? round(($owingTuition / $totalTuition) * 100, 2) : 0,
                'total_students' => $students->count(),
                'class_average_latest_due' => $classAverageLatestDue,
                'chart_data' => $chartData,
                'table_data' => $tuitionByStudent, // Student-level data for table display
            ], 'Tuition paid and owed retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
