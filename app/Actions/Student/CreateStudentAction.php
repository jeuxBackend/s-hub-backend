<?php

namespace App\Actions\Student;

use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Enums\GenderType;
use App\Enums\TermType;

class CreateStudentAction
{
    public function handle(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            $calculatedAge = !empty($data['dob']) ? Carbon::parse($data['dob'])->age : ($data['age'] ?? null);

            // Handle profile picture upload
            if (!empty($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
                $data['profile_picture'] = $data['profile_picture']->store('student_profiles', 'public');
            }

            // Create student
            $student = Student::create([
                'first_name' => $data['first_name'],
                'sur_name' => $data['sur_name'],
                'student_phone_number' => $data['student_phone_number'] ?? null,
                'term' => TermType::from($data['term']),
                'classroom_id' => $data['classroom_id'],
                'gender' => GenderType::from($data['gender']),
                'dob' => $data['dob'],
                'age' => $calculatedAge,
                'religion' => $data['religion'] ?? null,
                'profile_picture' => $data['profile_picture'] ?? null,
                'guardian_id' => $data['guardian_id'], // FK to users table
                'address' => $data['address'] ?? null,
                'institution_id' => auth()->user()->institution->id,
                'registration_number' => "student_" . time() . "_" . rand(1000, 9999), // Generate a unique registration number
                'created_by' => auth()->id(),
            ]);

            return $student;
        });
    }
}
