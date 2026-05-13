<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\ManagerInvoice;
use Illuminate\Http\Request;

class ActivitiesController extends Controller
{
    public function getInvoices()
    {
        $invoices = ManagerInvoice::where('manager_id', auth()->user()->id)->get();

        return $this->successResponse($invoices, 'Invoices retrieved successfully');
    }
}
