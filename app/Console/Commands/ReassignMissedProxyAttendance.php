<?php

namespace App\Console\Commands;

use App\Actions\Teacher\FindFreeTeachersAction;
use App\Models\NotificationLog;
use App\Models\Subject;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReassignMissedProxyAttendance extends Command
{
    protected $signature = 'attendance:reassign-missed-proxy';

    protected $description = 'Reassign proxy classes when the current proxy teacher does not mark attendance in time.';

    private const RESPONSE_GRACE_MINUTES = 3; // Changed from 1 to 3 minutes

    private const MINUTES_REQUIRED_FOR_NEW_PROXY = 15;

    public function handle(
        FindFreeTeachersAction $findFreeTeachersAction,
        FirebaseNotificationService $firebaseNotificationService
    ): int {
        $now = Carbon::now();

        \Log::info('Cron checking for missed proxy attendance at ' . $now->format('Y-m-d H:i:s'));

        $subjects = Subject::where('is_proxy', true)
            ->whereNotNull('proxy_teacher_id')
            ->whereNotNull('proxy_start_time')
            ->whereNotNull('proxy_end_time')
            ->with(['teacher', 'classroom', 'institution.principal'])
            ->get();

        $processed = 0;
        $reassigned = 0;
        $cleared = 0;
        $escalated = 0;
        $skipped = 0;

        foreach ($subjects as $subject) {
            $processed++;

            if (!$subject->institution || !$subject->teacher) {
                \Log::debug("Proxy subject {$subject->id} missing teacher or institution, skipping");
                $skipped++;
                continue;
            }

            $today = $now->toDateString();
            $currentProxyTeacherId = (int) $subject->proxy_teacher_id;
            $currentProxyTeacher = User::find($currentProxyTeacherId);

            if (!$currentProxyTeacher) {
                \Log::warning("Proxy subject {$subject->id} has an invalid proxy teacher id {$currentProxyTeacherId}, skipping");
                $skipped++;
                continue;
            }

            $attendanceExists = NotificationLog::attendanceRequestCompleted(
                (int) $subject->id,
                $today,
                $currentProxyTeacherId
            );

            $originalTeacherAttendanceCompleted = NotificationLog::attendanceRequestCompleted(
                (int) $subject->id,
                $today,
                (int) $subject->teacher_id
            );

            if ($originalTeacherAttendanceCompleted) {
                $this->clearProxyState($subject);
                NotificationLog::expireAttendanceRequest(
                    NotificationLog::attendanceRequestKey(
                        (int) $subject->id,
                        $today,
                        $currentProxyTeacherId
                    )
                );
                $cleared++;
                \Log::info("Original teacher attendance completed for subject {$subject->id}; proxy state cleared.");
                continue;
            }

            if ($attendanceExists) {
                $this->clearProxyState($subject);
                $cleared++;
                \Log::info("Proxy attendance already completed for subject {$subject->id}; proxy state cleared.");
                continue;
            }

            $proxyStartTime = Carbon::parse($subject->proxy_start_time);
            $proxyEndTime = Carbon::parse($subject->proxy_end_time);
            $proxyResponseDeadline = $proxyStartTime->copy()->addMinutes(self::RESPONSE_GRACE_MINUTES); // Now 3 minutes
            $latestSafeAssignmentTime = $proxyEndTime->copy()->subMinutes(self::MINUTES_REQUIRED_FOR_NEW_PROXY);

            if ($now->lessThan($proxyResponseDeadline)) {
                \Log::debug("Proxy response grace window still active for subject {$subject->id}, skipping reassignment.");
                $skipped++;
                continue;
            }

            if ($now->greaterThanOrEqualTo($latestSafeAssignmentTime)) {
                $this->notifyPrincipalProxyExpired($subject, $currentProxyTeacher, $firebaseNotificationService, $now);
                $escalated++;
                continue;
            }

            $candidateTeacherIds = $findFreeTeachersAction->handle((int) $subject->id, (int) $subject->institution_id);
            $candidateTeacherIds = array_values(array_diff(
                $candidateTeacherIds,
                [
                    $currentProxyTeacherId,
                    (int) $subject->teacher_id,
                ]
            ));

            if (!empty($candidateTeacherIds)) {
                $alreadyNotifiedTeacherIds = NotificationLog::query()
                    ->where('type', 'proxy_class_assignment')
                    ->whereDate('sent_at', $today)
                    ->whereJsonContains('meta->subject_id', (string) $subject->id)
                    ->pluck('user_id')
                    ->map(fn($userId) => (int) $userId)
                    ->all();

                $candidateTeacherIds = array_values(array_diff(
                    $candidateTeacherIds,
                    $alreadyNotifiedTeacherIds
                ));
            }

            if (empty($candidateTeacherIds)) {
                $this->notifyPrincipalNoProxyAvailable($subject, $currentProxyTeacher, $firebaseNotificationService, $now);
                $escalated++;
                continue;
            }

            $nextProxyTeacher = User::whereIn('id', $candidateTeacherIds)
                ->where('status', true)
                ->orderBy('id')
                ->first();

            if (!$nextProxyTeacher) {
                $this->notifyPrincipalNoProxyAvailable($subject, $currentProxyTeacher, $firebaseNotificationService, $now);
                $escalated++;
                continue;
            }

            $attendanceRequestKey = NotificationLog::attendanceRequestKey(
                (int) $subject->id,
                $today,
                (int) $nextProxyTeacher->id
            );

            $subject->update([
                'is_proxy' => true,
                'proxy_teacher_id' => $nextProxyTeacher->id,
                'proxy_start_time' => $proxyStartTime,
                'proxy_end_time' => $proxyEndTime,
            ]);

            NotificationLog::expireAttendanceRequest(
                NotificationLog::attendanceRequestKey(
                    (int) $subject->id,
                    $today,
                    $currentProxyTeacherId
                )
            );

            $title = 'Proxy Class Reassigned';


            $message = "";

            if (!empty($subject->classroom->in_charge_id)) { // Fixed: was $lecture

                $message = "You have assigned {$subject->name} in {$subject->classroom->name} and marks student attendance also.";

            } else {

                $message = "You have assigned {$subject->name} in {$subject->classroom->name}.";

            }

            $log = NotificationLog::create([
                'user_id' => $nextProxyTeacher->id,
                'type' => 'proxy_class_assignment',
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'attendance_request_key' => $attendanceRequestKey,
                'meta' => [
                    'subject_id' => (string) $subject->id,
                    'subject_name' => $subject->name,
                    'classroom_id' => (string) $subject->classroom_id,
                    'classroom_name' => $subject->classroom?->name ?? 'N/A',
                    'start_time' => Carbon::parse($subject->start_time)->format('g:i a'),
                    'end_time' => Carbon::parse($subject->end_time)->format('g:i a'),
                    'proxy_start_time' => $proxyStartTime->format('g:i a'),
                    'proxy_end_time' => $proxyEndTime->format('g:i a'),
                    'original_teacher_id' => (string) $subject->teacher_id,
                    'original_teacher_name' => $subject->teacher->full_name,
                    'previous_proxy_teacher_id' => (string) $currentProxyTeacherId,
                    'previous_proxy_teacher_name' => $currentProxyTeacher->full_name,
                    'attendance_request_key' => $attendanceRequestKey,
                ],
                'sent_at' => now(),
            ]);

            \Log::info('Sending proxy reassignment notification to ' . $log);

            if ($nextProxyTeacher->notifications_enabled && $nextProxyTeacher->fcm_token) {
                $firebaseNotificationService->sendToToken(
                    $nextProxyTeacher->fcm_token,
                    $title,
                    $message,
                    [
                        'type' => 'proxy_class_assignment',
                        'subject_id' => (string) $subject->id,
                        'subject_name' => $subject->name,
                        'classroom_name' => $subject->classroom?->name ?? 'N/A',
                    ]
                );
            }

            \Log::info("Proxy subject {$subject->id} reassigned from teacher {$currentProxyTeacherId} to {$nextProxyTeacher->id}");
            $reassigned++;
        }

        $summary = "Processed: {$processed}, Reassigned: {$reassigned}, Cleared: {$cleared}, Escalated: {$escalated}, Skipped: {$skipped}";
        \Log::info("Missed proxy attendance cron completed. {$summary}");
        $this->info($summary);

        return Command::SUCCESS;
    }

    private function clearProxyState(Subject $subject): void
    {
        $subject->update([
            'is_proxy' => false,
            'proxy_teacher_id' => null,
            'proxy_start_time' => null,
            'proxy_end_time' => null,
        ]);
    }

    private function notifyPrincipalNoProxyAvailable(
        Subject $subject,
        User $currentProxyTeacher,
        FirebaseNotificationService $firebaseNotificationService,
        Carbon $now
    ): void {
        $principal = $subject->institution?->principal;

        if (!$principal) {
            return;
        }

        $attendanceRequestKey = NotificationLog::attendanceRequestKey(
            (int) $subject->id,
            $now->toDateString(),
            (int) $subject->proxy_teacher_id
        );

        $alreadySent = NotificationLog::query()
            ->where('user_id', $principal->id)
            ->where('type', 'proxy_reassignment_alert')
            ->whereDate('sent_at', $now->toDateString())
            ->whereJsonContains('meta->subject_id', (string) $subject->id)
            ->whereJsonContains('meta->proxy_teacher_id', (string) $subject->proxy_teacher_id)
            ->exists();

        if ($alreadySent) {
            return;
        }

        $title = 'Proxy Teacher Needs Attention';
        $message = "No available replacement teacher could be assigned for {$subject->name}.";

        $log = NotificationLog::create([
            'user_id' => $principal->id,
            'type' => 'proxy_reassignment_alert',
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'attendance_request_key' => $attendanceRequestKey,
            'meta' => [
                'recipient_role' => 'principal',
                'subject_id' => (string) $subject->id,
                'subject_name' => $subject->name,
                'classroom_id' => (string) $subject->classroom_id,
                'classroom_name' => $subject->classroom?->name ?? 'N/A',
                'proxy_teacher_id' => (string) $subject->proxy_teacher_id,
                'proxy_teacher_name' => $currentProxyTeacher->full_name,
                'start_time' => Carbon::parse($subject->start_time)->format('g:i a'),
                'end_time' => Carbon::parse($subject->end_time)->format('g:i a'),
                'attendance_request_key' => $attendanceRequestKey,
            ],
            'sent_at' => now(),
        ]);

        if ($principal->notifications_enabled && $principal->fcm_token) {
            $firebaseNotificationService->sendToToken(
                $principal->fcm_token,
                $title,
                $message,
                [
                    'type' => 'proxy_reassignment_alert',
                    'subject_id' => (string) $subject->id,
                    'subject_name' => $subject->name,
                    'classroom_name' => $subject->classroom?->name ?? 'N/A',
                ]
            );
        }

        \Log::warning("No proxy replacement available for subject {$subject->id}; principal notified.");
    }

    private function notifyPrincipalProxyExpired(
        Subject $subject,
        User $currentProxyTeacher,
        FirebaseNotificationService $firebaseNotificationService,
        Carbon $now
    ): void {
        $principal = $subject->institution?->principal;

        if (!$principal) {
            return;
        }

        $alreadySent = NotificationLog::query()
            ->where('user_id', $principal->id)
            ->where('type', 'proxy_reassignment_expired')
            ->whereDate('sent_at', $now->toDateString())
            ->whereJsonContains('meta->subject_id', (string) $subject->id)
            ->whereJsonContains('meta->proxy_teacher_id', (string) $subject->proxy_teacher_id)
            ->exists();

        if ($alreadySent) {
            return;
        }

        $attendanceRequestKey = NotificationLog::attendanceRequestKey(
            (int) $subject->id,
            $now->toDateString(),
            (int) $subject->proxy_teacher_id
        );

        $title = 'Proxy Session Expired';
        $message = "The proxy session for {$subject->name} has expired before attendance was marked.";

        $log = NotificationLog::create([
            'user_id' => $principal->id,
            'type' => 'proxy_reassignment_expired',
            'title' => $title,
            'message' => $message,
            'is_read' => false,
            'attendance_request_key' => $attendanceRequestKey,
            'meta' => [
                'recipient_role' => 'principal',
                'subject_id' => (string) $subject->id,
                'subject_name' => $subject->name,
                'classroom_id' => (string) $subject->classroom_id,
                'classroom_name' => $subject->classroom?->name ?? 'N/A',
                'proxy_teacher_id' => (string) $subject->proxy_teacher_id,
                'proxy_teacher_name' => $currentProxyTeacher->full_name,
                'start_time' => Carbon::parse($subject->start_time)->format('g:i a'),
                'end_time' => Carbon::parse($subject->end_time)->format('g:i a'),
                'attendance_request_key' => $attendanceRequestKey,
            ],
            'sent_at' => now(),
        ]);

        if ($principal->notifications_enabled && $principal->fcm_token) {
            $firebaseNotificationService->sendToToken(
                $principal->fcm_token,
                $title,
                $message,
                [
                    'type' => 'proxy_reassignment_expired',
                    'subject_id' => (string) $subject->id,
                    'subject_name' => $subject->name,
                    'classroom_name' => $subject->classroom?->name ?? 'N/A',
                ]
            );
        }

        \Log::warning("Proxy session expired for subject {$subject->id}; principal notified.");
    }
}