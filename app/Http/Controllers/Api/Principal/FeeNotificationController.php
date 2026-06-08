<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Actions\Parent\NotifyUnpaidFeeParentsAction;
use App\Models\FeeReminderSchedule;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Facades\Log;

class FeeNotificationController extends Controller
{
    /**
     * Handle fee payment notifications (instant or scheduled)
     * 
     * @param Request $request
     * @param NotifyUnpaidFeeParentsAction $notifyAction
     * @return \Illuminate\Http\JsonResponse
     */
    public function notify(Request $request, NotifyUnpaidFeeParentsAction $notifyAction)
    {
        $request->validate([
            'type' => 'required|in:instant,scheduled',
            'title' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:1000',
            // Schedule-specific fields
            'notification_time' => 'required_if:type,scheduled|date_format:H:i',
            'days_of_week' => 'nullable|array|required_if:type,scheduled',
            'days_of_week.*' => 'integer|min:1|max:7',
            'is_enabled' => 'boolean',
        ]);

        try {
            $principal = auth()->user();
            $institutionId = $principal->institution_id;

            if (!$institutionId) {
                return $this->errorResponse('No institution associated with your account.', 400);
            }

            $notificationType = $request->input('type');

            // Handle instant notification
            if ($notificationType === 'instant') {
                return $this->handleInstantNotification($request, $notifyAction, $institutionId);
            }

            // Handle scheduled notification
            return $this->handleScheduledNotification($request, $institutionId);

        } catch (Throwable $e) {
            Log::error('Error in fee notification: ' . $e->getMessage());
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get current fee reminder schedule
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSchedule()
    {
        try {
            $principal = auth()->user();
            $institutionId = $principal->institution_id;

            if (!$institutionId) {
                return $this->errorResponse('No institution associated with your account.', 400);
            }

            $schedule = FeeReminderSchedule::where('institution_id', $institutionId)->first();

            if (!$schedule) {
                return $this->successResponse(
                    [
                        'has_schedule' => false,
                        'schedule' => null,
                    ],
                    'No fee reminder schedule configured.'
                );
            }

            return $this->successResponse(
                [
                    'has_schedule' => true,
                    'schedule' => [
                        'id' => $schedule->id,
                        'notification_time' => substr($schedule->notification_time, 0, 5),
                        'days_of_week' => $schedule->days_of_week,
                        'days_names' => $this->getDayNames($schedule->days_of_week),
                        'title' => $schedule->title,
                        'message' => $schedule->message,
                        'is_enabled' => $schedule->is_enabled,
                        'last_sent_at' => $schedule->last_sent_at?->toDateTimeString(),
                        'created_at' => $schedule->created_at->toDateTimeString(),
                        'updated_at' => $schedule->updated_at->toDateTimeString(),
                    ],
                ],
                'Fee reminder schedule retrieved successfully.'
            );

        } catch (Throwable $e) {
            Log::error('Error in getSchedule: ' . $e->getMessage());
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Handle instant notification request
     */
    private function handleInstantNotification(Request $request, NotifyUnpaidFeeParentsAction $notifyAction, int $institutionId)
    {
        $result = $notifyAction->handle(
            $institutionId,
            $request->input('title'),
            $request->input('message')
        );

        return $this->successResponse(
            array_merge($result, ['type' => 'instant']),
            $result['message']
        );
    }

    /**
     * Handle scheduled notification request
     */
    private function handleScheduledNotification(Request $request, int $institutionId)
    {
        $schedule = FeeReminderSchedule::updateOrCreate(
            ['institution_id' => $institutionId],
            [
                'notification_time' => $request->input('notification_time') . ':00',
                'days_of_week' => $request->input('days_of_week'),
                'title' => $request->input('title', 'Tuition Fee Payment Reminder'),
                'message' => $request->input('message', 'This is a reminder to pay your outstanding tuition fees. Please complete your payment at your earliest convenience.'),
                'is_enabled' => $request->input('is_enabled', false),
            ]
        );

        $wasCreated = $schedule->wasRecentlyCreated;

        return $this->successResponse(
            [
                'type' => 'scheduled',
                'id' => $schedule->id,
                'notification_time' => substr($schedule->notification_time, 0, 5),
                'days_of_week' => $schedule->days_of_week,
                'days_names' => $this->getDayNames($schedule->days_of_week),
                'title' => $schedule->title,
                'message' => $schedule->message,
                'is_enabled' => $schedule->is_enabled,
                'last_sent_at' => $schedule->last_sent_at?->toDateTimeString(),
            ],
            'Fee reminder schedule ' . ($wasCreated ? 'created' : 'updated') . ' successfully.'
        );
    }

    /**
     * Helper method to convert day numbers to names
     */
    private function getDayNames(?array $days): array
    {
        if (empty($days)) {
            return ['Every day'];
        }

        $dayMap = [
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            7 => 'Sunday',
        ];

        return array_map(fn($day) => $dayMap[$day] ?? 'Unknown', $days);
    }
}
