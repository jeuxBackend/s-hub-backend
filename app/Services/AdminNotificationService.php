<?php

namespace App\Services;

use App\Enums\AdminRole;
use App\Events\NewNotificationEvent;
use App\Models\Admin;
use App\Models\GeneralReport;
use App\Models\Institution;
use App\Models\ManagerInvoice;
use App\Models\NotificationLog;
use App\Models\SchoolAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * In-app + push notifications for admin-side accounts (admin, sub_admin,
 * sub_sub_admin, manager, sub_manager). Every public method is safe to call
 * from any flow: a failure here is logged and never breaks the caller.
 */
class AdminNotificationService
{
    /**
     * Store an in-app notification for each recipient, broadcast it live and
     * send a push when the account has an fcm_token.
     *
     * @param  iterable<Admin>|Admin|null  $recipients
     */
    public function send($recipients, string $type, string $title, string $message, array $meta = []): void
    {
        try {
            $list = $recipients instanceof Admin ? [$recipients] : collect($recipients ?? [])->all();

            $seen = [];
            foreach ($list as $admin) {
                if (!$admin instanceof Admin || isset($seen[$admin->id]) || $admin->status !== 'active') {
                    continue;
                }
                $seen[$admin->id] = true;

                $this->deliver($admin, $type, $title, $message, $meta);
            }
        } catch (Throwable $e) {
            Log::error('Admin notification failed', ['type' => $type, 'error' => $e->getMessage()]);
        }
    }

    private function deliver(Admin $admin, string $type, string $title, string $message, array $meta): void
    {
        try {
            $notification = NotificationLog::create([
                'admin_id' => $admin->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'is_read' => false,
                'meta' => $meta,
            ]);
        } catch (Throwable $e) {
            Log::error('Could not store admin notification', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);

            return;
        }

        try {
            event(new NewNotificationEvent($notification));
        } catch (Throwable $e) {
            Log::error('Admin notification broadcast failed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
        }

        if (!$admin->fcm_token) {
            return;
        }

        try {
            $data = array_map(
                fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v),
                array_merge($meta, ['type' => $type, 'notification_id' => $notification->id])
            );

            app(FirebaseNotificationService::class)->sendToToken($admin->fcm_token, $title, $message, $data);
        } catch (Throwable $e) {
            Log::error('Admin push notification failed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Recipient resolvers
    // ------------------------------------------------------------------

    /**
     * Platform admins, plus sub-admins / sub-sub-admins that hold the
     * permission (when given) and whose scope covers at least one of the
     * schools (an unrestricted scope covers everything).
     */
    public function adminLevelRecipients(?string $permission, array $institutionIds = []): Collection
    {
        return Admin::whereIn('role', [
            AdminRole::Admin->value,
            AdminRole::SubAdmin->value,
            AdminRole::SubSubAdmin->value,
        ])->where('status', 'active')->get()->filter(function (Admin $admin) use ($permission, $institutionIds) {
            if ($admin->role === AdminRole::Admin) {
                return true;
            }

            if ($permission !== null && !in_array($permission, $admin->effectivePermissions(), true)) {
                return false;
            }

            $scope = $admin->assignedInstitutionIds();

            return $scope === null || empty($institutionIds) || !empty(array_intersect($scope, $institutionIds));
        })->values();
    }

    /**
     * The manager of a school and the sub-manager it is assigned to.
     */
    public function schoolManagers(Institution $school): Collection
    {
        return Admin::whereIn('id', array_filter([$school->manager_id, $school->sub_manager_id]))
            ->where('status', 'active')
            ->get();
    }

    private function managerInstitutionIds(int $managerId): array
    {
        return Institution::where('manager_id', $managerId)->pluck('id')->all();
    }

    // ------------------------------------------------------------------
    // Events
    // ------------------------------------------------------------------

    public function schoolSubmitted(Institution $school): void
    {
        $this->send(
            $this->adminLevelRecipients('School_Requests', [$school->id]),
            'school_request',
            'New school request',
            "{$school->name} was submitted for approval.",
            ['school_id' => $school->id]
        );
    }

    public function schoolDecision(Institution $school, string $decision): void
    {
        $approved = $decision === 'approved';

        $this->send(
            $this->schoolManagers($school),
            'school_' . $decision,
            $approved ? 'School approved' : 'School rejected',
            $approved ? "{$school->name} has been approved." : "{$school->name} has been rejected.",
            ['school_id' => $school->id, 'status' => $decision]
        );
    }

    public function invoiceChanged(ManagerInvoice $invoice, string $change): void
    {
        $manager = Admin::find($invoice->manager_id);

        $this->send(
            $manager,
            'manager_invoice_' . $change,
            $change === 'created' ? 'New invoice' : 'Invoice updated',
            "Invoice {$invoice->invoice_number} for {$invoice->currency} {$invoice->total_amount} is " . ($change === 'created' ? 'ready.' : 'updated.'),
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'status' => $invoice->status]
        );
    }

    public function paymentSubmitted(ManagerInvoice $invoice): void
    {
        $ids = $invoice->institution_id ? [$invoice->institution_id] : $this->managerInstitutionIds($invoice->manager_id);
        $manager = Admin::find($invoice->manager_id);
        $who = $manager ? trim($manager->first_name . ' ' . $manager->last_name) : 'A manager';

        $this->send(
            $this->adminLevelRecipients('Invoices', $ids),
            'manager_payment_submitted',
            'Payment awaiting confirmation',
            "{$who} submitted payment for invoice {$invoice->invoice_number}.",
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number, 'manager_id' => $invoice->manager_id]
        );
    }

    public function paymentConfirmed(ManagerInvoice $invoice): void
    {
        $this->send(
            Admin::find($invoice->manager_id),
            'manager_payment_confirmed',
            'Payment confirmed',
            "Your payment for invoice {$invoice->invoice_number} has been confirmed.",
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number]
        );
    }

    public function reportToAdmin(GeneralReport $report): void
    {
        if ($report->reported_to_role !== AdminRole::Admin->value) {
            return;
        }

        $ids = $report->institution_id ? [$report->institution_id] : [];
        if (!$ids && $report->reporter_type === Admin::class) {
            $ids = $this->managerInstitutionIds((int) $report->reporter_id);
        }

        $this->send(
            $this->adminLevelRecipients('Reports', $ids),
            'general_report',
            'New report',
            $report->title,
            ['report_id' => $report->id]
        );
    }

    public function schoolAlert(SchoolAlert $alert): void
    {
        $school = Institution::find($alert->institution_id);
        if (!$school) {
            return;
        }

        $recipients = $this->schoolManagers($school)
            ->merge($this->adminLevelRecipients(null, [$school->id]));

        $label = ucfirst($alert->type);

        $this->send(
            $recipients,
            'school_alert',
            "{$label} alert at {$school->name}",
            $alert->message ?: "A {$alert->type} alert is active.",
            ['alert_id' => $alert->id, 'school_id' => $school->id, 'alert_type' => $alert->type, 'alert_status' => $alert->status]
        );
    }

    public function welcome(Admin $account, string $createdBy): void
    {
        $this->send(
            $account,
            'account_created',
            'Welcome',
            "Your account was created by {$createdBy}.",
            ['role' => $account->role?->value ?? (string) $account->role]
        );
    }

    public function accountStatusChanged(Admin $account, bool $blocked): void
    {
        $this->send(
            $account,
            $blocked ? 'account_blocked' : 'account_unblocked',
            $blocked ? 'Account blocked' : 'Account unblocked',
            $blocked ? 'Your account has been blocked.' : 'Your account has been unblocked.',
            ['blocked' => $blocked]
        );
    }
}
