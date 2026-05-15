<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentInvoice;
use App\Actions\StudentInvoice\CreateStudentInvoiceAction;
use App\Actions\StudentInvoice\UpdateStudentInvoiceAction;
use App\Actions\StudentInvoice\DeleteStudentInvoiceAction;
use App\Actions\StudentInvoice\ListStudentInvoicesAction;
use App\Actions\StudentInvoice\GetStudentInvoiceAction;
use Throwable;

class StudentInvoiceController extends Controller
{
    public function index(Request $request, ListStudentInvoicesAction $action)
    {
        try {
            $items = $action->handle($request);
            return $this->paginatedResponse($items);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetStudentInvoiceAction $action)
    {
        try {
            $item = $action->handle($id);
            return $this->successResponse($item, 'Retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request, CreateStudentInvoiceAction $action)
    {
        try {
            $item = $action->handle($request->all());
            return $this->successResponse($item, 'Created successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, StudentInvoice $studentInvoice, UpdateStudentInvoiceAction $action)
    {
        try {
            $item = $action->handle($studentInvoice, $request->all());
            return $this->successResponse($item, 'Updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(StudentInvoice $studentInvoice, DeleteStudentInvoiceAction $action)
    {
        try {
            $action->handle($studentInvoice);
            return $this->successResponse(null, 'Deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
