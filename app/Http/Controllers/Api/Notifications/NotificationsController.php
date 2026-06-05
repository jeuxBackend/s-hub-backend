<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationsController extends Controller
{
    public function sendNoticeboard(Request $request)
    {
        $user = auth()->user();
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'send_to' => 'required|in:parents,teachers,all',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors()->all());
        }

        $query = User::where('institution_id', $user->institution_id);

        if ($request->send_to == 'all') {
            $query->whereIn('role', ['teacher', 'parent']);
        } elseif ($request->send_to == 'teachers') {
            $query->where('role', 'teacher');
        } elseif ($request->send_to == 'parents') {
            $query->where('role', 'parent');
        }

        $users = $query->get();

        foreach ($users as $user) {
            NotificationLog::create([
                'user_id' => $user->id,
                'type' => 'noticeboard',
                'title' => $request->title,
                'message' => $request->message,
                'is_read' => false,
                'meta' => [
                    'send_to' => $request->send_to,
                ],
                'sent_at' => now(),
            ]);
        }

        return $this->successResponse('Noticeboard sent successfully');
    }

    public function getUserNotifications(Request $request)
    {
        $user = auth()->user();
        $notifications = NotificationLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
        return $this->successResponse($notifications, 'Notifications fetched successfully');
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
