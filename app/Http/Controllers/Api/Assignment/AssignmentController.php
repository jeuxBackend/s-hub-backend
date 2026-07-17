<?php

namespace App\Http\Controllers\Api\Assignment;

use App\Http\Controllers\Controller;
use App\Actions\Assignment\StoreAssignmentAction;
use App\Actions\Assignment\UpdateAssignmentAction;
use App\Actions\Assignment\DeleteAssignmentAction;
use App\Http\Resources\AssignmentResource;
use App\Http\Requests\Assignment\StoreAssignmentRequest;
use App\Http\Requests\Assignment\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Subject;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use App\Support\TeacherSubjectAssignmentResolver;

class AssignmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('otp.verified');
        $this->middleware('role:teacher,school-admin');
    }

    public function index(Request $request)
    {
        $query = Assignment::with(['classroom', 'subject', 'teacher']);

        // Filter by authenticated teacher
        $query->where('teacher_id', auth()->id());

        // Optional filters
        if ($request->filled('class_id')) {
            $query->where('classroom_id', $request->class_id);
        }

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->subject_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->input('per_page', 15);
        
        try {
            $assignments = $query->paginate($perPage);
            
            // Verify that we got a proper paginator instance
            if (!$assignments instanceof LengthAwarePaginator) {
                // This shouldn't happen with paginate(), but just in case
                $assignments = $query->simplePaginate($perPage);
            }
        } catch (\Exception $e) {
            // Fallback in case of any pagination errors
            return $this->errorResponse('Error retrieving assignments: ' . $e->getMessage(), 500);
        }

        return $this->successResponse(
            [
                'data' => AssignmentResource::collection($assignments),
                'pagination' => [
                    'current_page' => $assignments->currentPage(),
                    'last_page' => $assignments->lastPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                ],
            ],
            'Assignments retrieved successfully'
        );
    }

    public function show(Assignment $assignment)
    {
        // Ensure the authenticated user is the owner of the assignment
        if ($assignment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized to view this assignment', 403);
        }

        $assignment->load(['classroom', 'subject', 'teacher']);

        return $this->successResponse(
            new AssignmentResource($assignment),
            'Assignment retrieved successfully'
        );
    }

    public function store(StoreAssignmentRequest $request, StoreAssignmentAction $storeAssignmentAction)
    {
        // Validate that the teacher is assigned to the classroom and subject
        $classroom = Classroom::find($request->class_id);
        $subject = Subject::find($request->subject_id);

        if (!$classroom || !$subject) {
            return $this->errorResponse('Invalid classroom or subject', 400);
        }

        $teacher = auth()->user();
        $canManageSubject = app(TeacherSubjectAssignmentResolver::class)
            ->teacherCanManage($teacher, $classroom->id, $subject->id);

        if (!$canManageSubject) {
            // Also check if teacher is assigned to the classroom in general (through classroom_teachers pivot)
            $isTeaching = $classroom->teachers()
                ->where('teacher_id', auth()->id())
                ->exists();

            if (!$isTeaching) {
                return $this->errorResponse('You are not authorized to create assignments for this classroom and subject', 403);
            }
        }

        $assignment = $storeAssignmentAction->handle($request->validated());

        return $this->successResponse(
            new AssignmentResource($assignment),
            'Assignment created successfully'
        );
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment, UpdateAssignmentAction $updateAssignmentAction)
    {
        // Ensure the authenticated user is the owner of the assignment
        if ($assignment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized to update this assignment', 403);
        }

        $assignment = $updateAssignmentAction->handle($assignment, $request->validated());

        return $this->successResponse(
            new AssignmentResource($assignment),
            'Assignment updated successfully'
        );
    }

    public function destroy(Assignment $assignment, DeleteAssignmentAction $deleteAssignmentAction)
    {
        // Ensure the authenticated user is the owner of the assignment
        if ($assignment->teacher_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized to delete this assignment', 403);
        }

        $deleteAssignmentAction->handle($assignment);

        return $this->successResponse(null, 'Assignment deleted successfully');
    }
}
