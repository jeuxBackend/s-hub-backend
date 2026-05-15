<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;

class DeleteStudentInvoiceAction
{
    public function handle(StudentInvoice $model)
    {
        $model->delete();
        return true;
    }
}
