<?php

namespace App\Actions\Parent;

use App\Models\User;
use App\Models\StudentInvoice;
use App\Models\NotificationLog;
use App\Events\NewNotificationEvent;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyUnpaidFeeParentsAction
{
    protected FirebaseNotificationService $notificationService;

    public function __construct(FirebaseNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Send notifications to all parents with unpaid invoices
     * 
     * @param int $institutionId Institution ID to filter students
     * @param string|null $title Custom notification title
     * @param string|null $message Custom notification message
     * @return array Notification results
     */
    public function handle(int $institutionId, ?string $title = null, ?string $message = null): array
    {
        try {
            // Get all unpaid or partial invoices for the institution
            $unpaidInvoices = StudentInvoice::whereHas('student', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId);
            })
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('due_amount', '>', 0)
            ->with(['student.guardian'])
            ->get();

            if ($unpaidInvoices->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No unpaid invoices found.',
                    'notified' => 0,
                    'failed' => 0,
                    'total_unpaid_invoices' => 0,
                    'total_amount_due' => 0,
                    'parents_notified' => [],
                ];
            }

            // Get unique parent IDs from unpaid invoices
            $parentIds = $unpaidInvoices
                ->pluck('student.guardian_id')
                ->filter()
                ->unique()
                ->values();

            if ($parentIds->isEmpty()) {
                return [
                    'success' => true,
                    'message' => 'No parents found for unpaid invoices.',
                    'notified' => 0,
                    'failed' => 0,
                    'total_unpaid_invoices' => $unpaidInvoices->count(),
                    'total_amount_due' => $unpaidInvoices->sum('due_amount'),
                    'parents_notified' => [],
                ];
            }

            // Get parents with FCM tokens
            $parents = User::whereIn('id', $parentIds)
                ->where('role', 'parent')
                ->get();

            $defaultTitle = 'Tuition Fee Payment Reminder';
            $defaultMessage = 'This is a reminder to pay your outstanding tuition fees. Please complete your payment at your earliest convenience.';

            $notificationTitle = $title ?? $defaultTitle;
            $notificationMessage = $message ?? $defaultMessage;

            $notified = 0;
            $failed = 0;
            $results = [];

            foreach ($parents as $parent) {
                try {
                    // Get parent's unpaid invoices summary
                    $parentInvoices = $unpaidInvoices->filter(function ($invoice) use ($parent) {
                        return $invoice->student->guardian_id === $parent->id;
                    });

                    $totalDue = $parentInvoices->sum('due_amount');
                    $invoiceCount = $parentInvoices->count();

                    // Create in-app notification
                    $notification = NotificationLog::create([
                        'user_id' => $parent->id,
                        'type' => 'fee_payment_reminder',
                        'title' => $notificationTitle,
                        'message' => $notificationMessage,
                        'is_read' => false,
                        'meta' => [
                            'total_unpaid_invoices' => (string) $invoiceCount,
                            'total_amount_due' => (string) $totalDue,
                            'currency' => 'PKR',
                            'invoices' => $parentInvoices->map(function ($invoice) {
                                return [
                                    'invoice_id' => (string) $invoice->id,
                                    'student_name' => $invoice->student->full_name,
                                    'for_month' => $invoice->for_month,
                                    'for_year' => (string) $invoice->for_year,
                                    'due_amount' => (string) $invoice->due_amount,
                                    'status' => $invoice->status,
                                ];
                            })->toArray(),
                        ],
                        'sent_at' => now(),
                    ]);

                    // Broadcast in-app notification via WebSocket
                    event(new NewNotificationEvent($notification));

                    // Send Firebase push notification if token exists
                    if ($parent->fcm_token) {
                        $this->notificationService->sendToToken(
                            $parent->fcm_token,
                            $notificationTitle,
                            $notificationMessage,
                            [
                                'type' => 'fee_payment_reminder',
                                'total_due' => (string) $totalDue,
                                'invoice_count' => (string) $invoiceCount,
                            ]
                        );
                    }

                    $notified++;
                    $results[] = [
                        'parent_id' => $parent->id,
                        'parent_name' => $parent->full_name,
                        'total_due' => $totalDue,
                        'invoice_count' => $invoiceCount,
                        'status' => 'success',
                    ];

                    Log::info("Fee reminder sent to parent {$parent->id} ({$parent->full_name})");

                } catch (Throwable $e) {
                    $failed++;
                    $results[] = [
                        'parent_id' => $parent->id,
                        'parent_name' => $parent->full_name ?? 'Unknown',
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];

                    Log::error("Failed to send fee reminder to parent {$parent->id}: " . $e->getMessage());
                }
            }

            return [
                'success' => $failed === 0,
                'message' => $notified > 0 ? 'Fee reminders sent successfully' : 'Failed to send fee reminders',
                'notified' => $notified,
                'failed' => $failed,
                'total_unpaid_invoices' => $unpaidInvoices->count(),
                'total_amount_due' => $unpaidInvoices->sum('due_amount'),
                'unique_parents' => $parentIds->count(),
                'parents_notified' => $results,
            ];

        } catch (Throwable $e) {
            Log::error('Error in NotifyUnpaidFeeParentsAction: ' . $e->getMessage());
            throw $e;
        }
    }
}
