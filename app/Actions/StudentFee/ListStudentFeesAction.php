<?php

namespace App\Actions\StudentFee;

use App\Models\StudentFee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStudentFeesAction
{
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = StudentFee::with(['student', 'classroom']);

        // Ensure users only see records for their own institution
        if (auth()->check() && auth()->user()->institution_id) {
            $query->whereHas('student', function ($q) {
                $q->where('institution_id', auth()->user()->institution_id);
            });
        }

        if (!empty($filters['search'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('sur_name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('registration_number', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['month'])) {
            $query->where('payment_month', $filters['month']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 10);
    }
}
