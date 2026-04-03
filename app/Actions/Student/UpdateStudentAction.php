<?php

namespace App\Actions\Student;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;

class UpdateStudentAction
{
    public function handle(array $data, Student $student): Student
    {
        // Handle profile picture update
        if (!empty($data['profile_picture']) && $data['profile_picture']->isValid()) {
            if ($student->profile_picture) {
                Storage::disk('public')->delete($student->profile_picture);
            }

            $data['profile_picture'] = $data['profile_picture']->store('student_profiles', 'public');
        }

        $student->update($data);

        return $student->fresh(['classroom', 'guardian']);
    }
}
