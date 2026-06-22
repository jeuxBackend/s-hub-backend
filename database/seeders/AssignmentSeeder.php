<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use App\Models\Student;

class AssignmentSeeder extends Seeder
{
    public function run()
    {
        // Get sample data for seeding
        $classrooms = Classroom::limit(3)->get();
        $subjects = Subject::limit(3)->get();
        $teachers = User::where('role', 'teacher')->limit(2)->get();
        $students = Student::limit(5)->get();

        if ($classrooms->isEmpty() || $subjects->isEmpty() || $teachers->isEmpty()) {
            $this->command->info('No classrooms, subjects, or teachers found. Skipping assignment seeding.');
            return;
        }

        foreach ($teachers as $teacher) {
            foreach ($classrooms as $classroom) {
                foreach ($subjects as $subject) {
                    // Create 2-3 assignments per teacher-classroom-subject combination
                    for ($i = 0; $i < rand(2, 3); $i++) {
                        Assignment::create([
                            'title' => 'Assignment ' . ($i + 1) . ' - ' . $subject->name, // Assignment title
                            'assignment_text' => 'This is a sample assignment for ' . $subject->name . '. Please complete all exercises.', // assignment_text
                            'classroom_id' => $classroom->id, // clss_id
                            'subject_id' => $subject->id,
                            'teacher_id' => $teacher->id,
                            'status' => $i % 3 === 0 ? 'draft' : 'assigned', // status
                            'submission_end_date' => now()->addDays(rand(7, 30)), // submission_end_date
                            'assignment_date' => now(), // assignment_date
                        ]);
                    }
                }
            }
        }

        $this->command->info('Sample assignments created successfully.');
    }
}