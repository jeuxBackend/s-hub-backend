<?php

namespace App\Actions\StudentInvoice;

use App\Models\StudentInvoice;

class UpdateStudentInvoiceAction
{
    public function handle(StudentInvoice $model, array $data)
    {
        $model->update($data);
        return $model;
    }
}
