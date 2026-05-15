<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\StudentPerformance;
use App\Actions\StudentPerformance\CreateStudentPerformanceAction;
use App\Actions\StudentPerformance\UpdateStudentPerformanceAction;
use App\Actions\StudentPerformance\DeleteStudentPerformanceAction;
use App\Actions\StudentPerformance\ListStudentPerformancesAction;
use App\Actions\StudentPerformance\GetStudentPerformanceAction;
use Throwable;

class StudentPerformanceController extends Controller
{
    public function index(Request $request, ListStudentPerformancesAction $action)
    {
        try {
            $items = $action->handle($request);
            return $this->paginatedResponse($items);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function show($id, GetStudentPerformanceAction $action)
    {
        try {
            $item = $action->handle($id);
            return $this->successResponse($item, 'Retrieved successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function store(Request $request, CreateStudentPerformanceAction $action)
    {
        try {
            $item = $action->handle($request->all());
            return $this->successResponse($item, 'Created successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function update(Request $request, StudentPerformance $studentPerformance, UpdateStudentPerformanceAction $action)
    {
        try {
            $item = $action->handle($studentPerformance, $request->all());
            return $this->successResponse($item, 'Updated successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    public function destroy(StudentPerformance $studentPerformance, DeleteStudentPerformanceAction $action)
    {
        try {
            $action->handle($studentPerformance);
            return $this->successResponse(null, 'Deleted successfully');
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
