<?php

namespace App\Http\Controllers\Api\Manager;

use App\Actions\Manager\GetManagerAcademicFiltersAction;
use App\Actions\Manager\GetManagerAcademicStudentsAction;
use App\Actions\Manager\GetManagerAcademicTrendAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicPerformanceStudentResource;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class AcademicPerformanceController extends Controller
{
    /**
     * Schools, classes and subjects for the page's dropdowns/filters.
     */
    public function filters(GetManagerAcademicFiltersAction $action)
    {
        try {
            $data = $action->handle($this->managerInstitutionIds());
            return $this->successResponse($data, 'Academic performance filters retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Backs the Student / Toppers in school / Toppers in regions tabs.
     */
    public function students(Request $request, GetManagerAcademicStudentsAction $action)
    {
        try {
            $institutionIds = $this->managerInstitutionIds();

            $request->validate([
                'search' => 'nullable|string|max:255',
                'institution_id' => ['nullable', 'exists:institutions,id', Rule::in($institutionIds)],
                'classroom_id' => 'nullable|exists:classrooms,id',
                'region' => 'nullable|string|max:255',
                'term' => ['nullable', Rule::in(\App\Enums\TermType::values())],
                'sort' => ['nullable', Rule::in(['default', 'performance_desc'])],
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1',
            ]);

            $students = $action->handle($institutionIds, $request->all());

            return $this->paginatedResponse(
                AcademicPerformanceStudentResource::collection($students),
                'Academic performance students retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Backs the School / Class / Subject graph tabs.
     */
    public function trend(Request $request, GetManagerAcademicTrendAction $action)
    {
        try {
            $institutionIds = $this->managerInstitutionIds();

            $data = $request->validate([
                'type' => ['required', Rule::in(['school', 'class', 'subject'])],
                'id' => 'required|integer',
                'year' => 'nullable|integer|min:2000|max:2100',
            ]);

            $result = $action->handle($institutionIds, $data['type'], (int) $data['id'], $data['year'] ?? null);

            return $this->successResponse($result, 'Academic performance trend retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    private function managerInstitutionIds(): array
    {
        return Institution::where('manager_id', auth()->id())->pluck('id')->all();
    }
}
