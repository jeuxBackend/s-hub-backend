<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Models\TeacherAttendance;
use Illuminate\Http\Request;
use App\Http\Resources\TeacherAttendanceResource;
use App\Http\Resources\TeacherAttendanceCollection;

class TeacherAttendanceController extends Controller
{
    /**
     * Return paginated teacher attendance records for a given teacher ID.
     * Accessible only to principals (and school admins) via the principal route group.
     */
    public function index(Request $request, $teacherId)
    {
        try {
            $query = TeacherAttendance::where('teacher_id', $teacherId)
                ->orderBy('date', 'desc');

            // Optional filters: date range
            if ($request->filled('from')) {
                $query->whereDate('date', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('date', '<=', $request->input('to'));
            }

            $paginated = $query->paginate($request->input('per_page', 20));

            $items = $paginated->items();

            return response()->json([
                'data' => TeacherAttendanceResource::collection($items),
                'meta' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                ],
            ]);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
