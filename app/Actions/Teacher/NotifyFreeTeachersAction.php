<?php

namespace App\Actions\Teacher;

use App\Models\User;
use App\Models\Subject;
use App\Models\TeacherAttendance;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Throwable;

class NotifyFreeTeachersAction
{
    protected FirebaseNotificationService $notificationService;

    public function __construct(FirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send notifications to free teachers and mark their attendance as extra
     * 
     * @param int $lectureId Subject/Lecture ID
     * @param array $freeTeacherIds Array of teacher IDs to notify
     * @param string $message Notification message
     * @return array Array with notification results
     */
    public function handle(int $lectureId, array $freeTeacherIds, string $message = null)
    {
        $lecture = Subject::find($lectureId);

        if (!$lecture) {
            throw new \Exception('Lecture not found');
        }

        if (empty($freeTeacherIds)) {
            return [
                'success' => false,
                'message' => 'No free teachers available',
                'notified' => 0,
            ];
        }

        $notified = 0;
        $failed = 0;
        $results = [];

        // Get teachers with FCM tokens
        $teachers = User::whereIn('id', $freeTeacherIds)
            ->whereNotNull('fcm_token')
            ->get();

        $today = Carbon::today()->toDateString();

        foreach ($teachers as $teacher) {
            try {
                // Mark attendance as extra for today
                TeacherAttendance::updateOrCreate(
                    [
                        'teacher_id' => $teacher->id,
                        'subject_id' => $lecture->id,
                        'date' => $today,
                    ],
                    [
                        'institution_id' => $lecture->institution_id,
                        'status' => 'present',
                        'type' => 'extra',
                        'is_remote' => false,
                    ]
                );

                // Send notification
                $notificationTitle = 'Extra Class Assignment';
                $notificationBody = $message ?? "You have been assigned to an extra class for {$lecture->name}";

                $this->notificationService->sendToToken(
                    $teacher->fcm_token,
                    $notificationTitle,
                    $notificationBody,
                    [
                        'subject_id' => (string) $lecture->id,
                        'subject_name' => $lecture->name,
                        'classroom_id' => (string) $lecture->classroom_id,
                        'type' => 'extra_class_assignment',
                    ]
                );

                $notified++;
                $results[] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->first_name . ' ' . $teacher->last_name . ' ' . $teacher->sur_name,
                    'status' => 'success',
                ];

            } catch (Throwable $e) {
                $failed++;
                $results[] = [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->first_name . ' ' . $teacher->last_name . ' ' . $teacher->sur_name,
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => $failed === 0,
            'notified' => $notified,
            'failed' => $failed,
            'results' => $results,
        ];
    }
}
