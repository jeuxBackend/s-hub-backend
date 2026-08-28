<?php

namespace App\Actions\User;

use App\Enums\UserRole;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\SchoolAlert;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class GetUserAction
{
    public function handle(int $id): User
    {
        $user = User::findOrFail($id);

        // Gate::authorize('view', $user);
        $user->load([
            'institution.category',
            'guardianStudents.classroom',
            'guardianStudents.studentInvoices',
        ]);

        if (in_array($user->role, [UserRole::Principal, UserRole::SchoolAdmin, UserRole::Teacher, UserRole::Parent], true) && $user->institution_id && $user->institution) {
            $institutionId = $user->institution_id;

            $activeAlertsQuery = SchoolAlert::where('institution_id', $user->institution_id)
                ->excludingExpiredAbduction();

            if ($user->role === UserRole::Principal) {
                $activeAlertsQuery->where('status', '!=', 'resolved');
            } else {
                $activeAlertsQuery
                    ->where('status', 'active')
                    ->withinActiveCountWindow();
            }

            $user->institution->setAttribute(
                'active_alerts_count',
                $activeAlertsQuery->count()
            );

            if (in_array($user->role, [UserRole::Teacher, UserRole::SchoolAdmin], true)) {
                $user->institution->setAttribute(
                    'potential_abduction_alerts_count',
                    SchoolAlert::where('institution_id', $user->institution_id)
                        ->where('type', 'abduction')
                        ->where('status', 'potential')
                        ->excludingExpiredAbduction()
                        ->count()
                );
            }

            if ($user->role === UserRole::Principal || $user->role === UserRole::SchoolAdmin) {
                $user->institution->setAttribute(
                    'students_count',
                    Student::where('institution_id', $institutionId)->count()
                );

                $user->institution->setAttribute(
                    'teachers_count',
                    User::where('institution_id', $institutionId)
                        ->where('role', UserRole::Teacher->value)
                        ->count()
                );

                $user->institution->setAttribute(
                    'parents_count',
                    User::where('institution_id', $institutionId)
                        ->where('role', UserRole::Parent->value)
                        ->count()
                );

                $user->institution->setAttribute(
                    'school_admins_count',
                    User::where('institution_id', $institutionId)
                        ->where('role', UserRole::SchoolAdmin->value)
                        ->count()
                );

                $user->institution->setAttribute(
                    'classrooms_count',
                    Classroom::where('institution_id', $institutionId)->count()
                );
            }
        }

        return $user;
    }
}
