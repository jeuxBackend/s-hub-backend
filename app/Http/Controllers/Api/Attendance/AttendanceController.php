<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Actions\Attendance\MarkAttendanceAction;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Http\Requests\Attendance\GetAttendanceByDateRequest;
use App\Actions\Attendance\GetAttendanceByDateAction;
use App\Http\Requests\Attendance\GetAttendanceByMonthRequest;
use App\Actions\Attendance\GetAttendanceByMonthAction;
use App\Http\Resources\StudentAttendanceResource;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function mark(MarkAttendanceRequest $request, MarkAttendanceAction $action)
    {
        $attendance = $action->execute($request->validated() + ['recorded_by' => auth()->id()]);

        if (! $attendance) {
            return $this->errorResponse('Attendance record not found or unauthorized.', 404);
        }

        return $this->successResponse(
            new StudentAttendanceResource($attendance),
            'Attendance marked successfully'
        );
    }

    public function getByDate(GetAttendanceByDateRequest $request, GetAttendanceByDateAction $action)
    {
        $filters = $request->validated();
        $isPaginated = $filters['paginate'] ?? false;

        $result = $action->handle($filters);

        // Case: single record for a student (non-paginated)
        if (($filters['student_id'] ?? false) && ! $isPaginated) {
            $attendance = $result->first();

            return $attendance
                ? $this->successResponse(new StudentAttendanceResource($attendance))
                : $this->errorResponse('Attendance record not found.', 404);
        }

        // Case: paginated result
        if ($isPaginated && $result instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
            return $this->paginatedResponse(
                StudentAttendanceResource::collection($result),
                'Attendance records retrieved successfully.'
            );
        }

        // Case: all records (non-paginated)
        return $this->successResponse(
            StudentAttendanceResource::collection($result),
            'Attendance records retrieved successfully.'
        );
    }

    public function byDay(Request $request)
    {
        // TODO: Implement fetch attendance by day logic
    }

    public function getByMonth(GetAttendanceByMonthRequest $request, GetAttendanceByMonthAction $action)
    {
        $filters = $request->validated();
        $paginate = $filters['paginate'] ?? false;

        $result = $action->handle($filters, $paginate);

        return $paginate
            ? $this->paginatedResponse($result, 'Monthly attendance fetched successfully.')
            : $this->successResponse($result, 'Monthly attendance fetched successfully.');
    }
}
