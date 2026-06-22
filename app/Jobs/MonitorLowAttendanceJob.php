<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\NotificationLog;
use App\Models\User;
use App\Enums\AttendanceStatus;
use App\Enums\UserRole;
use App\Events\NewNotificationEvent;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MonitorLowAttendanceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * This job now serves as a reconciliation mechanism to handle any edge cases
     * where attendance flags might not have been updated properly in real-time.
     */
    public function handle()
    {
        Log::info('Running low attendance monitoring reconciliation job');

        $presentValue = AttendanceStatus::Present->value;
        $excusedValue = AttendanceStatus::Excused->value;

        // Get all students and recalculate their attendance to ensure flags are correct
        $students = Student::with('classroom')->get();
        $fixedCount = 0;

        foreach ($students as $student) {
            // Calculate overall attendance percentage for the student across all subjects
            $attendanceStats = DB::table('student_attendances')
                ->select(
                    DB::raw('COUNT(*) as total_attendance'),
                    DB::raw("SUM(CASE WHEN status = '{$presentValue}' THEN 1 ELSE 0 END) as present_count"),
                    DB::raw("ROUND((SUM(CASE WHEN status = '{$presentValue}' THEN 1 ELSE 0 END) * 100.0 / COUNT(*)), 2) as attendance_percentage")
                )
                ->where('student_id', $student->id)
                ->where('status', '<>', $excusedValue) // Exclude excused absences from calculation
                ->first();

            if ($attendanceStats->total_attendance > 0) {
                $attendancePercentage = (float)$attendanceStats->attendance_percentage;
                $absencePercentage = 100 - $attendancePercentage;
                $shouldFlag = $attendancePercentage < 70;

                // Check if the stored flag matches what it should be
                if ($student->is_flag !== $shouldFlag) {
                    Log::info("Reconciling student {$student->id}: attendance={$attendancePercentage}%, was_flagged={$student->is_flag}, should_be_flagged={$shouldFlag}");
                    
                    // Update the flag to the correct value
                    $student->update(['is_flag' => $shouldFlag]);
                    $fixedCount++;
                }
            }
        }

        Log::info("Low attendance monitoring reconciliation completed. Fixed {$fixedCount} student flags.");
    }
}