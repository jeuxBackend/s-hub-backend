<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentFeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // return parent::toArray($request);
        $invoiceRecords = collect($this->student?->studentInvoices ?? []);

        // Group invoices by invoice_uuid when present, otherwise by the invoice id.
        // For invoices that share the same invoice_uuid, only consider the latest
        // invoice (by created_at, falling back to max id) when computing owing.
        $grouped = $invoiceRecords->groupBy(fn($invoice) => $invoice->invoice_uuid ?: 'inv_'.$invoice->id);

        $invoiceTotalAmount = 0.0;
        $invoicePaidAmount = 0.0;
        $invoiceDueAmount = 0.0;

        foreach ($grouped as $group) {
            $latest = $group->sortByDesc('created_at')->values()->first() ?? $group->sortByDesc('id')->first();
            if ($latest) {
                $groupTotal = (float) $group->max('total_amount');
                $groupPaid = (float) $group->sum('paid_amount');
                $groupOwing = max(0.0, $groupTotal - $groupPaid);

                $invoiceTotalAmount += $groupTotal;
                $invoicePaidAmount += $groupPaid;
                $invoiceDueAmount += $groupOwing;
            }
        }
        return [
            'id'           => $this->id,
            'student_id'   => $this->student_id,
            'student'      => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'first_name' => $this->student->first_name,
                    'last_name' => $this->student->last_name,
                    'sur_name' => $this->student->sur_name,
                    'profile_picture' => $this->student->profile_picture,
                    'registration_number' => $this->student->registration_number,
                ];
            }),
            'classroom'    => $this->whenLoaded('classroom', function () {
                return [
                    'id' => $this->classroom->id,
                    'name' => $this->classroom->name,
                ];
            }),
            // Map DB columns to old API keys
            'tuition_fee'  => $this->tuition_fee,
            'uniform_fee'  => $this->uniform_fee,
            'meals'        => $this->meals_fee,
            'books'        => $this->books_fee,
            'others'       => $this->other_fee,
            'paid'         => $invoiceTotalAmount > 0 ? $invoicePaidAmount : $this->paid_amount,
            'total_amount' => $invoiceTotalAmount > 0 ? $invoiceTotalAmount : $this->total_amount,
            'owing'        => $invoiceTotalAmount > 0 ? $invoiceDueAmount : max(0, ($this->total_amount ?? 0) - ($this->paid_amount ?? 0)),
            'due_date'     => $this->due_date?->toDateString(),
            'term'         => $this->class_id, // Mapping the new 'class_id' back to 'term' for the API
            'status'       => $this->status instanceof \App\Enums\PaymentStatusType ? $this->status->value : $this->status,
        ];
    }
}
