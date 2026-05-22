<?php

namespace App\Actions\Parent;

use App\Models\StudentInvoice;
use Illuminate\Http\Request;

class ListParentInvoicesAction
{
    public function handle(int $parentId, Request $request)
    {
        $status = $request->query('status');
        $studentId = $request->query('student_id');

        $query = StudentInvoice::query()
            ->with(['student.institution.manager'])
            ->whereHas('student', function ($q) use ($parentId) {
                $q->where('guardian_id', $parentId);
            });

        if ($status) {
            $query->where('status', $status);
        }

        if ($studentId) {
            $query->where('student_id', $studentId);
        }

        return $query->latest()->paginate($request->get('per_page', 10));
    }
}
