<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Manager\GetManagerTuitionOverviewAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\TuitionOverviewResource;
use Illuminate\Http\Request;
use Throwable;

class TuitionController extends Controller
{
    /**
     * Per-student tuition totals (invoiced / paid / owing) across every
     * school this manager owns. Read-only — Tuition Management page.
     */
    public function index(Request $request, GetManagerTuitionOverviewAction $action)
    {
        try {
            $request->validate([
                'search' => 'nullable|string|max:255',
                'institution_id' => 'nullable|integer|exists:institutions,id',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $manager = auth()->user();
            $institutionIds = $manager->institutions()->pluck('id')->all();

            $students = $action->handle($institutionIds, $request->all());

            return $this->paginatedResponse(
                TuitionOverviewResource::collection($students),
                'Tuition overview retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
