<?php

namespace App\Actions\Subject;

use App\Models\Subject;
use App\Models\User;

class GetSubjectAction
{
    /**
     * Handle fetching a single subject's details.
     *
     * @param  int  $id
     * @param  User  $requester
     * @return \App\Models\Subject
     */
    public function handle(int $id, User $requester): Subject
    {
        // Check if the user is authorized to view the subject

        // if ($requester->role !== 'principal' && $requester->role !== 'school_admin') {
        //     abort(403, 'Unauthorized access to this subject.');
        // }

        // Find the subject by ID and return it
        return Subject::findOrFail($id);
    }
}
