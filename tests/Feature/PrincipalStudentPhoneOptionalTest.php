<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrincipalStudentPhoneOptionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_principal_can_create_a_child_without_phone_number(): void
    {
        [$principal, $parent, $classroom] = $this->principalParentAndClassroom();

        $this->actingAs($principal, 'sanctum');

        $response = $this->postJson('/api/v1/principal/students', [
            'first_name' => 'Ali',
            'sur_name' => 'Khan',
            'gender' => 'male',
            'dob' => '2018-01-15',
            'term' => 'first',
            'classroom_id' => $classroom->id,
            'guardian_id' => $parent->id,
            'address' => 'Test address',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.student_phone_number', null);

        $this->assertDatabaseHas('students', [
            'first_name' => 'Ali',
            'sur_name' => 'Khan',
            'guardian_id' => $parent->id,
            'student_phone_number' => null,
        ]);
    }

    public function test_principal_can_update_a_child_without_phone_number(): void
    {
        [$principal, $parent, $classroom] = $this->principalParentAndClassroom();

        $student = Student::create([
            'first_name' => 'Sara',
            'sur_name' => 'Ahmed',
            'student_phone_number' => '1234567890',
            'gender' => 'female',
            'dob' => '2017-05-10',
            'age' => 9,
            'term' => 'first',
            'classroom_id' => $classroom->id,
            'institution_id' => $principal->institution_id,
            'guardian_id' => $parent->id,
            'registration_number' => 'student_test_1001',
            'created_by' => $principal->id,
        ]);

        $this->actingAs($principal, 'sanctum');

        $response = $this->patchJson("/api/v1/principal/students/{$student->id}", [
            'student_phone_number' => null,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.student_phone_number', null);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'student_phone_number' => null,
        ]);
    }

    private function principalParentAndClassroom(): array
    {
        $manager = Admin::create([
            'first_name' => 'Manager',
            'sure_name' => 'Admin',
            'email' => 'manager@example.com',
            'phone_number' => '9000000001',
            'password' => Hash::make('password'),
            'role' => 'sub_admin',
        ]);

        $institution = Institution::create([
            'manager_id' => $manager->id,
            'name' => 'Test School',
            'email' => 'school@example.com',
            'phone_number' => '9000000002',
            'status' => 'approved',
        ]);

        $principal = User::create([
            'email' => 'principal@example.com',
            'phone_number' => '9000000003',
            'password' => Hash::make('password'),
            'role' => 'principal',
            'otp_verified' => true,
            'institution_id' => $institution->id,
            'first_name' => 'Prime',
            'sur_name' => 'Principal',
        ]);

        $parent = User::create([
            'email' => 'parent@example.com',
            'phone_number' => '9000000004',
            'password' => Hash::make('password'),
            'role' => 'parent',
            'otp_verified' => true,
            'institution_id' => $institution->id,
            'first_name' => 'Parent',
            'sur_name' => 'User',
        ]);

        $classroom = Classroom::create([
            'institution_id' => $institution->id,
            'name' => 'Grade 1',
            'code' => 'G1',
        ]);

        return [$principal, $parent, $classroom];
    }
}
