<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentInvoice;
use App\Actions\StudentInvoice\CreateStudentInvoiceAction;
use App\Actions\StudentInvoice\UpdateStudentInvoiceAction;
use App\Actions\StudentInvoice\DeleteStudentInvoiceAction;
use App\Actions\StudentInvoice\ListStudentInvoicesAction;
use App\Actions\StudentInvoice\GetStudentInvoiceAction;
use App\Actions\StudentInvoice\GenerateInvoiceReceiptPdfAction;
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

    public function pay(Request $request, StudentInvoice $studentInvoice)
    {
        $request->validate([
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'paid_date' => ['required', 'date'],
            'payment_method' => ['required', 'in:cash,card,check'],
        ]);

        try {
            if ($studentInvoice->status === 'paid') {
                return $this->errorResponse('Invoice is already paid.', 400);
            }

            $paidAmount = (float) $request->input('paid_amount');
            $invoiceTotal = (float) $studentInvoice->total_amount;

            if ($paidAmount > $invoiceTotal) {
                return $this->errorResponse('Paid amount cannot exceed invoice total.', 422);
            }

            return DB::transaction(function () use ($request, $studentInvoice, $paidAmount) {
                $paymentDate = $request->input('paid_date');
                $paymentMethod = $request->input('payment_method');
                $invoiceUuid = $studentInvoice->invoice_uuid ?: \Illuminate\Support\Str::uuid()->toString();

                $studentInvoice->update([
                    'invoice_uuid' => $invoiceUuid,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $studentInvoice->total_amount - $paidAmount,
                    'status' => $paidAmount === (float) $studentInvoice->total_amount ? 'paid' : 'partial',
                    'payment_date' => $paymentDate,
                    'payment_method' => $paymentMethod,
                ]);

                if ($paidAmount < (float) $studentInvoice->total_amount) {
                    $remainingAmount = $studentInvoice->total_amount - $paidAmount;
                    $paidBy = $studentInvoice->paid_by ?: $studentInvoice->student->guardian_id ?? auth()->id();

                    StudentInvoice::create([
                        'student_id' => $studentInvoice->student_id,
                        'paid_by' => $paidBy,
                        'for_month' => $studentInvoice->for_month,
                        'for_year' => $studentInvoice->for_year,
                        'amount' => $remainingAmount,
                        'discount' => 0,
                        'total_amount' => $remainingAmount,
                        'paid_amount' => 0,
                        'due_amount' => $remainingAmount,
                        'status' => 'unpaid',
                        'payment_date' => null,
                        'payment_method' => null,
                        'reference_no' => null,
                        'invoice_uuid' => $invoiceUuid,
                    ]);
                }

                return $this->successResponse($studentInvoice->fresh(), 'Invoice payment recorded successfully.');
            });
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function downloadReceipt(StudentInvoice $studentInvoice, GenerateInvoiceReceiptPdfAction $action)
    {
        try {
            return $action->handle($studentInvoice);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
