<?php

namespace App\Actions\Institution;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class DeleteSchoolAction
{
    public function handle($id)
    {
        $school = Institution::findOrFail($id);

        // users.institution_id has no cascade/set-null rule, so deleting an
        // institution that still has users attached would otherwise fail
        // with a raw foreign-key constraint violation. withTrashed() because
        // the constraint applies to the physical row regardless of Eloquent's
        // soft-delete scope.
        if (User::withTrashed()->where('institution_id', $school->id)->exists()) {
            throw ValidationException::withMessages([
                'institution' => ['This institution still has users (teachers, parents, principals, or school admins) assigned to it. Reassign or remove them before deleting.'],
            ]);
        }

        $school->delete();
        return true;
    }
}
