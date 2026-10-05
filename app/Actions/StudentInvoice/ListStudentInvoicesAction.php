<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;
use Illuminate\Http\Request;

class ListStudentInvoicesAction
{
    public function handle(Request $request)
    {
        $user = auth()->user();

        $query = StudentInvoice::query()
            ->whereHas('student', function ($q) use ($user, $request) {
                $q->where('institution_id', $user->institution_id);

                if ($request->filled('class_id')) {
                    $q->where('classroom_id', $request->input('class_id'));
                }
            });

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query->latest()->paginate($request->get('per_page', 10));
    }
}
