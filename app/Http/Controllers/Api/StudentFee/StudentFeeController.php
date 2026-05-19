<?php

namespace App\Http\Controllers\Api\StudentFee;

use App\Http\Controllers\Controller;
use App\Actions\StudentFee\ListStudentFeesAction;
use App\Http\Resources\StudentFeeResource;
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

            // Overview Data for Graphs
            $overviewQuery = StudentFee::query();
            if (auth()->check() && auth()->user()->institution_id) {
                $overviewQuery->whereHas('student', function ($q) {
                    $q->where('institution_id', auth()->user()->institution_id);
                });
            }

            // Apply the same month filter if provided? Usually graphs are either filtered by month or show all.
            // The user asked for "monthly based student payments", so we'll group by month.
            // If they passed a month, should the totals be only for that month?
            // "graphs like paid unpaid and monthly based student payments" -> probably overall for the institution.
            if (!empty($filters['month'])) {
                $overviewQuery->where('payment_month', $filters['month']);
            }
            if (!empty($filters['class_id'])) {
                $overviewQuery->where('class_id', $filters['class_id']);
            }

            $totalFees = (float) (clone $overviewQuery)->sum('total_amount');
            $totalPaid = (float) (clone $overviewQuery)->sum('paid_amount');
            $totalOwing = max(0, $totalFees - $totalPaid);

            $paidPercentage = $totalFees > 0 ? round(($totalPaid / $totalFees) * 100) : 0;
            $owingPercentage = $totalFees > 0 ? round(($totalOwing / $totalFees) * 100) : 0;

            // Monthly breakdown (ignoring the month filter for the chart so it shows the trend)
            $trendQuery = StudentFee::query();
            if (auth()->check() && auth()->user()->institution_id) {
                $trendQuery->whereHas('student', function ($q) {
                    $q->where('institution_id', auth()->user()->institution_id);
                });
            }
            if (!empty($filters['class_id'])) {
                $trendQuery->where('class_id', $filters['class_id']);
            }

            $monthlyPayments = $trendQuery
                ->selectRaw('payment_month as month, SUM(paid_amount) as total_paid')
                ->whereNotNull('payment_month')
                ->groupBy('payment_month')
                ->orderBy('payment_month')
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
}
