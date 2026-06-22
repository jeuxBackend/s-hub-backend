<?php

namespace App\Http\Controllers\Api\Assignment;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class ParentAssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('otp.verified');
        $this->middleware('role:parent');
    }

    public function index(Request $request)
    {
        // Get the authenticated parent's ID
        $parentId = auth()->id();

        // Get all children of this parent
        $childIds = Student::where('guardian_id', $parentId)->pluck('id');

        if ($childIds->isEmpty()) {
            return $this->successResponse(
                [
                    'data' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 15,
                        'total' => 0,
                    ],
                ],
                'No children found'
            );
        }

        $query = Assignment::with(['classroom', 'subject', 'teacher'])
            ->where('status', 'assigned') // Only show published assignments
            ->whereHas('classroom.students', function ($q) use ($childIds) {
                $q->whereIn('id', $childIds);  // Changed: using 'id' instead of 'student_id'
            });

        // Optional filters
        if ($request->filled('class_id')) {
            $query->where('classroom_id', $request->class_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        $assignments = $query->paginate($request->per_page ?? 15);

        // Ensure the result is a LengthAwarePaginator instance
        if (! $assignments instanceof LengthAwarePaginator) {
            return $this->errorResponse('Pagination failed', 500);
        }

        return $this->successResponse(
            [
                'data' => AssignmentResource::collection($assignments),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(), // Fixed method name
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                ],
            ],
            'Assignments retrieved successfully'
        );
    }

    public function show(Assignment $assignment)
    {
        // Verify that the assignment is assigned/published
        if ($assignment->status !== 'assigned') {
            return $this->errorResponse('Assignment not available', 404);
        }

        // Get the authenticated parent's ID
        $parentId = auth()->id();

        // Get all children of this parent
        $childIds = Student::where('guardian_id', $parentId)->pluck('id');

        if ($childIds->isEmpty()) {
            return $this->errorResponse('No children found for this parent', 404);
        }

        // Check if any of the parent's children are enrolled in the assignment's classroom
        $isChildEnrolled = $assignment->classroom->students()
            ->whereIn('id', $childIds)
            ->exists();

        if (!$isChildEnrolled) {
            return $this->errorResponse('Unauthorized to view this assignment', 403);
        }

        $assignment->load(['classroom', 'subject', 'teacher']);

        return $this->successResponse(
            new AssignmentResource($assignment),
            'Assignment retrieved successfully'
        );
    }

    public function assignmentsForChild(Request $request, $studentId)
    {
        // Verify that the authenticated parent is the guardian of the specified child
        $isGuardian = Student::where('id', $studentId)
            ->where('guardian_id', auth()->id())
            ->exists();

        if (!$isGuardian) {
            return $this->errorResponse('Unauthorized to view assignments for this child', 403);
        }

        $query = Assignment::with(['classroom', 'subject', 'teacher'])
            ->where('status', 'assigned') // Only show published assignments
            ->whereHas('classroom.students', function ($q) use ($studentId) {
                $q->where('id', $studentId);  // Changed: using 'id' instead of 'student_id'
            });

        // Optional filters
        if ($request->filled('class_id')) {
            $query->where('classroom_id', $request->class_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        $assignments = $query->paginate($request->per_page ?? 15);

        // Ensure the result is a LengthAwarePaginator instance
        if (! $assignments instanceof LengthAwarePaginator) {
            return $this->errorResponse('Pagination failed', 500);
        }

        return $this->successResponse(
            [
                'data' => AssignmentResource::collection($assignments),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(), // Fixed method name
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                ],
            ],
            'Assignments for child retrieved successfully'
        );
    }
}