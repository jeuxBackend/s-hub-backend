<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class NotificationsController extends Controller
{
    public function sendNoticeboard(Request $request, FirebaseNotificationService $firebaseNotificationService)
    {
        $authUser = auth()->user();
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'send_to' => 'required|in:parents,teachers,all',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->all());
        }

        $query = User::where('institution_id', $authUser->institution_id);

        if ($request->send_to == 'all') {
            $query->whereIn('role', ['teacher', 'parent']);
        } elseif ($request->send_to == 'teachers') {
            $query->where('role', 'teacher');
        } elseif ($request->send_to == 'parents') {
            $query->where('role', 'parent');
        }

        $users = $query->get();
        $pushSent = 0;
        $pushSkipped = 0;
        $pushFailed = 0;

        foreach ($users as $recipient) {
            NotificationLog::create([
                'user_id' => $recipient->id,
                'type' => 'noticeboard',
                'title' => $request->title,
                'message' => $request->message,
                'is_read' => false,
                'meta' => [
                    'send_to' => $request->send_to,
                ],
                'sent_at' => now(),
            ]);

            if (!$recipient->notifications_enabled || !$recipient->fcm_token) {
                $pushSkipped++;
                continue;
            }

            try {
                $sent = $firebaseNotificationService->sendToToken(
                    $recipient->fcm_token,
                    $request->title,
                    $request->message,
                    [
                        'type' => 'noticeboard',
                        'send_to' => $request->send_to,
                    ]
                );

                if ($sent) {
                    $pushSent++;
                } else {
                    $pushFailed++;
                    Log::warning('Noticeboard push notification was not sent', [
                        'user_id' => $recipient->id,
                        'send_to' => $request->send_to,
                    ]);
                }
            } catch (Throwable $e) {
                $pushFailed++;
                Log::error('Failed to send noticeboard push notification', [
                    'user_id' => $recipient->id,
                    'send_to' => $request->send_to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->successResponse([
            'notified_users' => $users->count(),
            'push_sent' => $pushSent,
            'push_skipped' => $pushSkipped,
            'push_failed' => $pushFailed,
        ], 'Noticeboard sent successfully');
    }

    public function getUserNotifications(Request $request)
    {
        $user = auth()->user();
        $notifications = NotificationLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
        $notifications = $notifications->map(function ($notification) {
            //if notification type is teacher_abent_alert and only 15 minutes left in class end time return is_expired true , we can get class_start_time and end time from meta which is json feild in database ,  


            $meta = $notification->meta;

            if ($notification->type == 'teacher_absent_alert') {
                $class_end_time = $meta['end_time'] ?? null;
                $endTime = $this->parseNotificationTime($class_end_time);

                if ($endTime) {
                    // Calculate minutes remaining until end time
                    $minutesRemaining = \Carbon\Carbon::now()->diffInMinutes($endTime, false);

                    // If 15 minutes or less remaining (or time has passed), mark as expired
                    if ($minutesRemaining <= 15) {
                        $notification->is_expired = true;
                    }
                }
            }

            return [
                'id' => $notification->id,
                'type' => $notification->type,
                'title' => $notification->title,
                'message' => $notification->message,
                'is_read' => $notification->is_read,
                'meta' => $notification->meta,
                'is_expired' => $notification->is_expired ? 'Yes' : 'No',
                'sent_at' => $notification->sent_at,
            ];

        });
        return $this->successResponse($notifications, 'Notifications fetched successfully');
    }

    private function parseNotificationTime(?string $time): ?\Carbon\Carbon
    {
        if (!$time) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('g:i a', strtolower(trim($time)));
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function readNotification(Request $request, $id)
    {
        $user = auth()->user();
        $notification = NotificationLog::where('user_id', $user->id)->where('id', $id)->first();
        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }
        $notification->update(['is_read' => true]);
        return $this->successResponse('Notification marked as read successfully');
    }

    /**
     * Mark all notifications as read for the authenticated user
     */
    public function markAllAsRead()
    {
        try {
            $user = auth()->user();

            $updated = NotificationLog::where('user_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return $this->successResponse(
                ['marked_count' => $updated],
                'All notifications marked as read successfully'
            );
        } catch (\Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get unread notification count for the authenticated user
     */
    public function getUnreadCount()
    {
        try {
            $user = auth()->user();

            $count = NotificationLog::where('user_id', $user->id)
                ->where('is_read', false)
                ->count();

            return $this->successResponse(
                ['unread_count' => $count],
                'Unread notification count retrieved successfully'
            );
        } catch (\Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
