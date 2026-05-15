<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;

class GetStudentInvoiceAction
{
    public function handle($id)
    {
        return StudentInvoice::findOrFail($id);
    }
}
