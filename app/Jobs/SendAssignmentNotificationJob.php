<?php

namespace App\Jobs;

use App\Models\Assignment;
use App\Models\Student;
use App\Models\NotificationLog;
use App\Services\FirebaseNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAssignmentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $assignment;

    /**
     * Create a new job instance.
     */
    public function __construct(Assignment $assignment)
    {
        $this->assignment = $assignment;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Only send notifications if the assignment status is 'assigned' (not draft)
        if ($this->assignment->status !== 'assigned') {
            return;
        }

        // Load related models
        $this->assignment->load(['classroom', 'subject', 'teacher']);

        // Get all students in the classroom
        $students = $this->assignment->classroom->students;

        // Process each student to notify their parents
        foreach ($students as $student) {
            // Get the parent/guardian of the student
            $parent = $student->guardian;

            if (!$parent) {
                continue; // Skip if no parent/guardian found
            }

            // Send FCM notification to parent
            $this->sendFcmNotification($parent, $student);

            // Create notification log record
            $this->createNotificationLog($parent, $student);
        }
    }

    /**
     * Send FCM notification to parent
     */
    private function sendFcmNotification($parent, $student): void
    {
        // Check if parent has FCM token and notifications enabled
        if (!$parent->fcm_token || !$parent->notifications_enabled) {
            return;
        }

        try {
            $firebaseService = new FirebaseNotificationService();

            $title = "New Assignment";
            $body = "A new assignment '{$this->assignment->title}' has been assigned to your child {$student->full_name} in {$this->assignment->subject->name}";

            $data = [
                'type' => 'assignment_created',
                'assignment_id' => (string) $this->assignment->id,
                'student_id' => (string) $student->id,
                'classroom_id' => (string) $this->assignment->classroom_id,
                'subject_id' => (string) $this->assignment->subject_id,
            ];

            $result = $firebaseService->sendToToken($parent->fcm_token, $title, $body, $data);

            if ($result) {
                Log::info("FCM assignment notification sent to parent {$parent->id}");
            } else {
                Log::warning("Failed to send FCM assignment notification to parent {$parent->id}");
            }
        } catch (\Exception $e) {
            Log::error("FCM Assignment Notification Error: " . $e->getMessage());
        }
    }

    /**
     * Create notification log record
     */
    private function createNotificationLog($parent, $student): void
    {
        NotificationLog::create([
            'user_id' => $parent->id,
            'student_id' => $student->id,
            'title' => 'New Assignment',
            'message' => "A new assignment '{$this->assignment->title}' has been assigned to your child {$student->full_name} in {$this->assignment->subject->name}",
            'type' => 'assignment_created',
            'data' => json_encode([
                'assignment_id' => $this->assignment->id,
                'student_id' => $student->id,
                'classroom_id' => $this->assignment->classroom_id,
                'subject_id' => $this->assignment->subject_id,
            ]),
            'is_read' => false,
            'is_expired' => false,
        ]);
    }
}