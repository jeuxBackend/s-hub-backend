<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;
use Illuminate\Http\Request;

class ListStudentInvoicesAction
{
    public function handle(Request $request)
    {
        return StudentInvoice::query()->latest()->paginate($request->get('per_page', 10));
    }
}
