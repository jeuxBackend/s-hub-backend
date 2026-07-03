<?php

namespace App\Http\Controllers\Api\Principal;

use App\Http\Controllers\Controller;
use App\Models\TeacherAttendance;
use App\Models\TimetableEntry;
use App\Models\User;
use App\Enums\AttendanceStatus;
use Illuminate\Http\Request;
use App\Http\Resources\TeacherAttendanceResource;
use Carbon\Carbon;
use Throwable;

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
                ->with(['subject.classroom'])
                ->orderBy('created_at', 'desc');

            // Optional filters: date range
            if ($request->filled('from')) {
                $query->whereDate('date', '>=', $request->input('from'));
            }
            if ($request->filled('to')) {
                $query->whereDate('date', '<=', $request->input('to'));
            }

            $paginated = $query->paginate($request->input('per_page', 20));

            // Load subject and classroom for each attendance record
            $items = $paginated->getCollection()->load(['subject.classroom']);

            // Compute summary statistics
            $total = $paginated->total();
            $presentCount = $items->where('status', AttendanceStatus::Present->value)->count();
            $absentCount = $items->where('status', AttendanceStatus::Absent->value)->count();
            $lateCount = $items->where('status', AttendanceStatus::Late->value)->count();

            $presentPct = $total ? round(($presentCount / $total) * 100, 2) : 0;
            $absentPct = $total ? round(($absentCount / $total) * 100, 2) : 0;
            $latePct = $total ? round(($lateCount / $total) * 100, 2) : 0;

            return response()->json([
                'data' => TeacherAttendanceResource::collection($items),
                'meta' => [
                    'total' => $total,
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    // summary stats
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                    'late_count' => $lateCount,
                    'present_percentage' => $presentPct,
                    'absent_percentage' => $absentPct,
                    'late_percentage' => $latePct,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    /**
     * Get all free teachers during a specific time range
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFreeTeachers(Request $request)
    {
        try {
            $request->validate([
                'start_time' => 'required|string',
                'end_time' => 'required|string',
            ]);

            //if only 15 minutes left in end_time then is_expire check should be true
            $is_expire = false;
            $endTime = Carbon::createFromFormat('g:i A', strtoupper($request->end_time));

            // Check if current time is within 15 minutes of end time (or past it)
            $minutesRemaining = Carbon::now()->diffInMinutes($endTime, false);
            if ($minutesRemaining <= 15) {
                $is_expire = true;
            }

            if ($is_expire) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Notification Expired ! ',
                        'data' => [
                            'time_range' => [
                                'start_time' => $request->start_time,
                                'end_time' => $request->end_time,
                            ],
                            'free_teachers' => [],
                            'total_free' => 0,
                            'is_expired' => 'Yes',
                        ],
                    ],
                    403
                );
            }

            $principal = auth()->user();
            $institutionId = $principal->institution_id;

            // Parse the provided times (supports both 12-hour with AM/PM and 24-hour format)
            try {
                $startTime = Carbon::createFromFormat('g:i A', strtoupper($request->start_time));
                if (!$startTime) {
                    // Try 24-hour format as fallback
                    $startTime = Carbon::createFromFormat('H:i', $request->start_time);
                }

                $endTime = Carbon::createFromFormat('g:i A', strtoupper($request->end_time));
                if (!$endTime) {
                    // Try 24-hour format as fallback
                    $endTime = Carbon::createFromFormat('H:i', $request->end_time);
                }

                if (!$startTime || !$endTime) {
                    return $this->errorResponse(
                        'Invalid time format. Use "10:00 AM" or "10:00" format.',
                        422
                    );
                }
            } catch (\Exception $e) {
                return $this->errorResponse(
                    'Invalid time format. Use "10:00 AM" or "10:00" format.',
                    422
                );
            }

            // Validate that end time is after start time
            if ($endTime->lessThanOrEqualTo($startTime)) {
                return $this->errorResponse(
                    'End time must be after start time.',
                    422
                );
            }



            // Get all teachers in the institution
            $allTeachers = User::where('institution_id', $institutionId)
                ->whereIn('role', ['teacher', 'school-admin'])
                ->with(['institution'])
                ->get();

            if ($allTeachers->isEmpty()) {
                return $this->successResponse(
                    [
                        'time_range' => [
                            'start_time' => $request->start_time,
                            'end_time' => $request->end_time,
                        ],
                        'free_teachers' => [],
                        'total_free' => 0,
                    ],
                    'No teachers found in your institution'
                );
            }

            $weekday = Carbon::now($principal->timezone ?? config('app.timezone', 'UTC'))->isoWeekday();

            $busyTeachers = TimetableEntry::query()
                ->where('institution_id', $institutionId)
                ->whereIn('teacher_id', $allTeachers->pluck('id'))
                ->where('weekday', $weekday)
                ->get()
                ->filter(function (TimetableEntry $entry) use ($startTime, $endTime) {
                    $entryStart = Carbon::parse($entry->start_time);
                    $entryEnd = Carbon::parse($entry->end_time);

                    return !($endTime->lessThanOrEqualTo($entryStart) || $startTime->greaterThanOrEqualTo($entryEnd));
                })
                ->pluck('teacher_id')
                ->unique()
                ->toArray();

            $proxyBusyTeachers = \App\Models\Subject::query()
                ->where('institution_id', $institutionId)
                ->whereIn('proxy_teacher_id', $allTeachers->pluck('id'))
                ->where('is_proxy', true)
                ->whereNotNull('proxy_start_time')
                ->whereNotNull('proxy_end_time')
                ->get()
                ->filter(function ($subject) use ($startTime, $endTime) {
                    $proxyStart = Carbon::parse($subject->proxy_start_time);
                    $proxyEnd = Carbon::parse($subject->proxy_end_time);

                    return !($endTime->lessThanOrEqualTo($proxyStart) || $startTime->greaterThanOrEqualTo($proxyEnd));
                })
                ->pluck('proxy_teacher_id')
                ->unique()
                ->toArray();

            $busyTeachers = array_unique(array_merge($busyTeachers, $proxyBusyTeachers));

            // Filter free teachers
            $freeTeachers = $allTeachers->filter(function ($teacher) use ($busyTeachers) {
                return !in_array($teacher->id, $busyTeachers);
            })->map(function ($teacher) {
                return [
                    'id' => $teacher->id,
                    'first_name' => $teacher->first_name,
                    'sur_name' => $teacher->sur_name,
                    'full_name' => $teacher->full_name,
                    'email' => $teacher->email,
                    'phone_number' => $teacher->phone_number,
                    'staff_number' => $teacher->staff_number,
                    'position' => $teacher->position,
                    'role' => $teacher->role,
                ];
            });

            return $this->successResponse(
                [
                    'time_range' => [
                        'start_time' => $request->start_time,
                        'end_time' => $request->end_time,
                    ],
                    'free_teachers' => $freeTeachers->values(),
                    'total_free' => $freeTeachers->count(),
                    'total_teachers' => $allTeachers->count(),
                ],
                'Free teachers retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }
}
