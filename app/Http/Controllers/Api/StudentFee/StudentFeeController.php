<?php

namespace App\Http\Controllers\Api\StudentFee;

use App\Http\Controllers\Controller;
use App\Actions\StudentFee\ListStudentFeesAction;
use App\Actions\StudentFee\UpdateFeeAction;
use App\Http\Requests\StudentFee\UpdateFeeRequest;
use App\Http\Resources\StudentFeeResource;
use App\Models\StudentInvoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Throwable;

use App\Models\StudentFee;

class StudentFeeController extends Controller
{
    public function index(Request $request, ListStudentFeesAction $listStudentFeesAction)
    {
        try {
            $filters = $request->only(['search', 'class_id', 'month', 'per_page', 'status']);

            $fees = $listStudentFeesAction->handle($filters);

            $resource = new ResourceCollection(StudentFeeResource::collection($fees));
            $resource->resource = $fees;

            $studentIds = $fees->pluck('student_id')->filter()->unique()->values();
            $totalFees = 0.0;
            $totalPaid = 0.0;
            $totalOwing = 0.0;
            $monthlyPayments = collect();

            if ($studentIds->isNotEmpty()) {
                // Overview Data for Graphs from StudentInvoice filtered by current fee students.
                $overviewQuery = StudentInvoice::query()
                    ->whereIn('student_id', $studentIds->all());

                if (!empty($filters['month'])) {
                    $overviewQuery->where('for_month', $filters['month']);
                }

                $invoiceRows = $overviewQuery->get();

                // Group invoices by invoice_uuid when present, otherwise by invoice id.
                // For invoices that share the same invoice_uuid, only consider the latest
                // invoice (by created_at, fallback to max id) when computing totals/owing.
                $grouped = $invoiceRows->groupBy(fn($invoice) => $invoice->invoice_uuid ?: 'inv_'.$invoice->id);

                $totalFees = 0.0;
                $totalPaid = 0.0;
                $totalOwing = 0.0;

                foreach ($grouped as $group) {
                    $latest = $group->sortByDesc('created_at')->values()->first() ?? $group->sortByDesc('id')->first();
                    if ($latest) {
                        $groupTotal = (float) $group->max('total_amount');
                        $groupPaid = (float) $group->sum('paid_amount');
                        $groupOwing = max(0.0, $groupTotal - $groupPaid);

                        $totalFees += $groupTotal;
                        $totalPaid += $groupPaid;
                        $totalOwing += $groupOwing;
                    }
                }

                $trendQuery = StudentInvoice::query()
                    ->whereIn('student_id', $studentIds->all());
                if (!empty($filters['month'])) {
                    $trendQuery->where('for_month', $filters['month']);
                }
                $monthlyPayments = $trendQuery
                    ->selectRaw('for_month as month, SUM(paid_amount) as total_paid')
                    ->whereNotNull('for_month')
                    ->groupBy('for_month')
                    ->orderBy('for_month')
                    ->get();
            }

            $paidPercentage = $totalFees > 0 ? round(($totalPaid / $totalFees) * 100) : 0;
            $owingPercentage = $totalFees > 0 ? round(($totalOwing / $totalFees) * 100) : 0;

            // Monthly breakdown from invoices
            $trendQuery = StudentInvoice::query();
            if (auth()->check() && auth()->user()->institution_id) {
                $trendQuery->whereHas('student', function ($q) {
                    $q->where('institution_id', auth()->user()->institution_id);
                });
            }
            if (!empty($filters['class_id'])) {
                $trendQuery->whereHas('student', function ($q) use ($filters) {
                    $q->where('classroom_id', $filters['class_id']);
                });
            }

            $monthlyPayments = $trendQuery
                ->selectRaw('for_month as month, SUM(paid_amount) as total_paid')
                ->whereNotNull('for_month')
                ->groupBy('for_month')
                ->orderBy('for_month')
                ->get();

            $extraMeta = [
                'overview' => [
                    'totals' => [
                        'tuition_paid' => $totalPaid,
                        'tuition_owed' => $totalOwing,
                    ],
                    'percentages' => [
                        'paid' => $paidPercentage,
                        'owing' => $owingPercentage,
                    ],
                    'monthly_payments' => $monthlyPayments
                ]
            ];

            return $this->paginatedResponse($resource, 'Student fees retrieved successfully', 200, $extraMeta);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(UpdateFeeRequest $request, StudentFee $studentFee, UpdateFeeAction $updateFeeAction)
    {
        try {
            $updatedFee = $updateFeeAction->handle($studentFee, $request->validated());

            return $this->successResponse(new StudentFeeResource($updatedFee), 'Student fee updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
