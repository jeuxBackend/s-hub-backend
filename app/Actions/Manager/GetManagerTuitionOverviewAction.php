<?php

namespace App\Actions\Manager;

use App\Models\Student;
use App\Models\StudentInvoice;

class GetManagerTuitionOverviewAction
{
    /**
     * Per-student tuition totals (invoiced / paid / owing) across every
     * school this manager owns — read-only, for the Tuition Management page.
     */
    public function handle(array $institutionIds, array $data = []): mixed
    {
        $query = Student::query()
            ->whereIn('institution_id', $institutionIds)
            ->with('institution:id,name')
            ->select('students.*')
            ->selectSub(
                StudentInvoice::selectRaw('COALESCE(SUM(total_amount), 0)')
                    ->whereColumn('student_id', 'students.id'),
                'total_tuition'
            )
            ->selectSub(
                StudentInvoice::selectRaw('COALESCE(SUM(paid_amount), 0)')
                    ->whereColumn('student_id', 'students.id'),
                'tuition_paid'
            )
            ->selectSub(
                StudentInvoice::selectRaw('COALESCE(SUM(due_amount), 0)')
                    ->whereColumn('student_id', 'students.id'),
                'tuition_owing'
            )
            ->selectSub(
                StudentInvoice::selectRaw('MAX(payment_date)')
                    ->whereColumn('student_id', 'students.id'),
                'last_payment_date'
            );

        if (!empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('sur_name', 'like', "%{$search}%")
                    ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        if (!empty($data['institution_id'])) {
            $query->where('institution_id', $data['institution_id']);
        }

        return $query->orderBy('first_name')->paginate($data['per_page'] ?? 20);
    }
}
