<?php

namespace App\Http\Controllers\Api\Chat;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Throwable;

class ChatUserController extends Controller
{
    /**
     * Returns the list of users the authenticated user is allowed to chat with,
     * filtered by their role and scoped to the same institution.
     *
     * Parent    → child's classroom teachers + the institution principal
     * Teacher   → all other teachers + principal + parents of students in their classrooms
     * Principal → all teachers + all parents in the institution
     */
    public function index()
    {
        try {
            $auth = auth()->user();
            $institutionId = $auth->institution_id;

            $contacts = match ($auth->role) {
                UserRole::Parent    => $this->contactsForParent($auth, $institutionId),
                UserRole::Teacher, UserRole::SchoolAdmin => $this->contactsForTeacher($auth, $institutionId),
                default             => collect(),
            };

            return $this->successResponse(
                $contacts->map(fn(User $u) => [
                    'id'              => $u->id,
                    'full_name'       => $u->full_name,
                    'role'            => $u->role?->value,
                    'position'        => $u->position,
                    'profile_picture' => $u->profile_picture,
                ])->values(),
                'Chat contacts retrieved successfully'
            );
        } catch (Throwable $e) {
            return $this->exceptionResponse($e);
        }
    }

    // ─── Role-specific contact builders ───────────────────────────────

    private function contactsForParent(User $parent, int $institutionId): \Illuminate\Support\Collection
    {
        // Get all classrooms the parent's children are enrolled in
        $classroomIds = Student::where('guardian_id', $parent->id)
            ->whereNotNull('classroom_id')
            ->pluck('classroom_id')
            ->unique();

        // Teachers assigned to those classrooms (via subjects or classroom_teachers)
        $teacherIds = \App\Models\Subject::whereIn('classroom_id', $classroomIds)
            ->whereNotNull('teacher_id')
            ->pluck('teacher_id')
            ->merge(
                \App\Models\ClassroomTeacher::whereIn('classroom_id', $classroomIds)
                    ->pluck('teacher_id')
            )
            ->unique();

        $allIds = $teacherIds->unique()->filter();

        return User::whereIn('id', $allIds)->get();
    }

    private function contactsForTeacher(User $teacher, int $institutionId): \Illuminate\Support\Collection
    {
        // All other teachers & school admins in the same institution
        $teacherIds = User::where('institution_id', $institutionId)
            ->whereIn('role', [UserRole::Teacher->value, UserRole::SchoolAdmin->value])
            ->where('id', '!=', $teacher->id)
            ->pluck('id');

        // Parents of students in classrooms where this teacher teaches
        $classroomIds = \App\Models\ClassroomTeacher::where('teacher_id', $teacher->id)
            ->pluck('classroom_id')
            ->merge(
                \App\Models\Subject::where('teacher_id', $teacher->id)
                    ->pluck('classroom_id')
            )
            ->unique();

        $parentIds = Student::whereIn('classroom_id', $classroomIds)
            ->whereNotNull('guardian_id')
            ->pluck('guardian_id')
            ->unique();

        $allIds = $teacherIds->merge($parentIds)->unique()->filter();

        return User::whereIn('id', $allIds)->get();
    }

}
