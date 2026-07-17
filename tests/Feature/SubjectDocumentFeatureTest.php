<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Classroom;
use App\Models\Institution;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectDocument;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubjectDocumentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_upload_study_materials_for_assigned_timetable_subject(): void
    {
        Storage::fake('local');

        [$institution, $teacher] = $this->createInstitutionUser('teacher', 'teacher1@example.com', '7000000001');
        $classroom = Classroom::create([
            'institution_id' => $institution->id,
            'name' => 'Grade 5',
            'code' => 'G5',
        ]);
        $subject = Subject::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'name' => 'Math',
            'code' => 'MTH',
            'teacher_id' => $teacher->id,
        ]);

        TimetableEntry::create([
            'institution_id' => $institution->id,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'weekday' => 1,
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $this->actingAs($teacher, 'sanctum');

        $response = $this->post('/api/v1/teacher/subject-documents', [
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'document_type' => 'study_material',
            'materials' => [
                [
                    'title' => 'Chapter 1 Notes',
                    'description' => 'Fractions intro',
                    'file' => UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf'),
                ],
                [
                    'title' => 'Worksheet',
                    'description' => 'Practice sheet',
                    'file' => UploadedFile::fake()->create('worksheet.xlsx', 300, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                ],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('subject_documents', 2);
        Storage::disk('local')->assertExists(SubjectDocument::first()->file_path);
    }

    public function test_teacher_cannot_upload_without_timetable_assignment(): void
    {
        Storage::fake('local');

        [$institution, $teacher] = $this->createInstitutionUser('teacher', 'teacher2@example.com', '7000000002');
        $classroom = Classroom::create([
            'institution_id' => $institution->id,
            'name' => 'Grade 6',
            'code' => 'G6',
        ]);
        $subject = Subject::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'name' => 'Science',
            'code' => 'SCI',
        ]);

        $this->actingAs($teacher, 'sanctum');

        $response = $this->post('/api/v1/teacher/subject-documents', [
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'document_type' => 'yearly_syllabus',
            'title' => 'Science Year Plan',
            'file' => UploadedFile::fake()->create('science.pdf', 120, 'application/pdf'),
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('subject_documents', 0);
    }

    public function test_parent_can_view_subject_documents_for_their_child_classroom(): void
    {
        Storage::fake('local');

        [$institution, $teacher] = $this->createInstitutionUser('teacher', 'teacher3@example.com', '7000000003');
        [, $parent] = $this->createInstitutionUser('parent', 'parent1@example.com', '7000000004');
        $classroom = Classroom::create([
            'institution_id' => $institution->id,
            'name' => 'Grade 7',
            'code' => 'G7',
        ]);
        $subject = Subject::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'name' => 'English',
            'code' => 'ENG',
        ]);

        Student::create([
            'first_name' => 'Child',
            'sur_name' => 'One',
            'registration_number' => 'REG-1001',
            'guardian_id' => $parent->id,
            'classroom_id' => $classroom->id,
            'institution_id' => $institution->id,
        ]);

        $document = SubjectDocument::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'document_type' => 'study_material',
            'title' => 'Grammar Notes',
            'description' => 'Week 1',
            'file_path' => 'teacher_uploads/subject_documents/grammar-notes.pdf',
            'file_original_name' => 'grammar-notes.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12345,
        ]);

        Storage::disk('local')->put($document->file_path, 'dummy');

        $this->actingAs($parent, 'sanctum');

        $this->getJson('/api/v1/parent/subject-documents')
            ->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Grammar Notes',
            ]);
    }

    public function test_principal_can_view_teacher_uploads_in_the_institution(): void
    {
        Storage::fake('local');

        [$institution, $teacher] = $this->createInstitutionUser('teacher', 'teacher4@example.com', '7000000005');
        [, $principal] = $this->createInstitutionUser('principal', 'principal1@example.com', '7000000006');
        $classroom = Classroom::create([
            'institution_id' => $institution->id,
            'name' => 'Grade 8',
            'code' => 'G8',
        ]);
        $subject = Subject::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'name' => 'History',
            'code' => 'HIS',
        ]);

        SubjectDocument::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'document_type' => 'yearly_syllabus',
            'title' => 'History Syllabus',
            'file_path' => 'teacher_uploads/subject_documents/history-syllabus.pdf',
            'file_original_name' => 'history-syllabus.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 12345,
        ]);

        $this->actingAs($principal, 'sanctum');

        $this->getJson('/api/v1/principal/subject-documents')
            ->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'History Syllabus',
            ]);
    }

    private function createInstitutionUser(string $role, string $email, string $phone): array
    {
        $admin = Admin::create([
            'first_name' => 'System',
            'sure_name' => 'Admin',
            'email' => 'admin-' . $email,
            'phone_number' => '9' . $phone,
            'password' => 'secret',
            'role' => 'manager',
        ]);

        $institution = Institution::create([
            'manager_id' => $admin->id,
            'name' => 'Test School ' . $phone,
            'email' => 'school-' . $email,
            'phone_number' => '8' . $phone,
        ]);

        $user = User::create([
            'email' => $email,
            'phone_number' => $phone,
            'password' => 'secret',
            'role' => $role,
            'otp_verified' => true,
            'status' => true,
            'first_name' => ucfirst($role),
            'sur_name' => 'User',
            'institution_id' => $institution->id,
        ]);

        return [$institution, $user];
    }
}
