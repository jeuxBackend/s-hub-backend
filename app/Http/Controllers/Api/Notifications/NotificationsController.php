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
        $notifications = NotificationLog::where('user_id', $user->id)->get();
        return $this->successResponse('Notifications fetched successfully', $notifications);
    }

    public function readNotification(Request $request, $id)
    {
        $user = auth()->user();
        $notification = NotificationLog::where('user_id', $user->id)->where('id', $id)->first();
        if (!$notification) {
            return $this->errorResponse('Notification not found', 404);
        }
        $notification->read_at = now();
        $notification->save();
        return $this->successResponse('Notification read successfully');
    }
}
