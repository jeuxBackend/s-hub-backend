<?php

namespace Tests\Feature;

use App\Actions\Rota\GenerateSchoolTimetableAction;
use App\Models\Admin;
use App\Models\Classroom;
use App\Models\ClassSubjectRequirement;
use App\Models\Institution;
use App\Models\SchoolTimetableConfig;
use App\Models\Subject;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableSystemFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_preserves_locked_entries_and_produces_conflict_free_full_preview(): void
    {
        $school = $this->createSchool();
        $config = $this->createConfig($school['institution']);
        [$classroomA, $classroomB] = $this->createClassrooms($school['institution']);
        [$teacherA, $teacherB] = $this->createTeachers($school['institution']);

        $mathA = $this->createSubject($school['institution'], $classroomA, $teacherA, 'Math A');
        $scienceA = $this->createSubject($school['institution'], $classroomA, $teacherA, 'Science A');
        $englishB = $this->createSubject($school['institution'], $classroomB, $teacherB, 'English B');

        ClassSubjectRequirement::create([
            'institution_id' => $school['institution']->id,
            'classroom_id' => $classroomA->id,
            'subject_id' => $mathA->id,
            'teacher_id' => $teacherA->id,
            'lessons_per_week' => 3,
        ]);
        ClassSubjectRequirement::create([
            'institution_id' => $school['institution']->id,
            'classroom_id' => $classroomA->id,
            'subject_id' => $scienceA->id,
            'teacher_id' => $teacherA->id,
            'lessons_per_week' => 2,
        ]);
        ClassSubjectRequirement::create([
            'institution_id' => $school['institution']->id,
            'classroom_id' => $classroomB->id,
            'subject_id' => $englishB->id,
            'teacher_id' => $teacherB->id,
            'lessons_per_week' => 2,
        ]);

        TimetableEntry::create([
            'institution_id' => $school['institution']->id,
            'config_id' => $config->id,
            'academic_year' => $config->academic_year,
            'term' => $config->term,
            'subject_id' => $mathA->id,
            'classroom_id' => $classroomA->id,
            'teacher_id' => $teacherA->id,
            'weekday' => 1,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:40:00',
            'entry_type' => 'lesson',
            'version' => 1,
            'is_locked' => true,
        ]);

        $result = app(GenerateSchoolTimetableAction::class)->handle($config->fresh(['workingDays', 'breakPeriods']));
        $allEntries = collect($result['all_entries']);

        $this->assertCount(6, $result['entries']);
        $this->assertCount(7, $result['all_entries']);
        $this->assertSame(1, $result['generation_summary']['locked_entries_preserved']);
        $this->assertSame(0, $this->countTeacherConflicts($allEntries));
        $this->assertSame(0, $this->countClassroomConflicts($allEntries));
        $this->assertSame(3, $allEntries->where('subject_id', $mathA->id)->count());
    }

    public function test_primary_mode_uses_class_in_charge_when_requirement_teacher_is_missing(): void
    {
        $school = $this->createSchool();
        $config = $this->createConfig($school['institution'], 'primary');
        $teacher = $this->createUser($school['institution'], 'teacher', 'class-teacher@example.com');
        $classroom = Classroom::create([
            'institution_id' => $school['institution']->id,
            'name' => 'Primary 1',
            'code' => 'P1',
            'in_charge_id' => $teacher->id,
        ]);
        $subject = $this->createSubject($school['institution'], $classroom, null, 'General Studies');

        ClassSubjectRequirement::create([
            'institution_id' => $school['institution']->id,
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => null,
            'lessons_per_week' => 2,
        ]);

        $result = app(GenerateSchoolTimetableAction::class)->handle($config->fresh(['workingDays', 'breakPeriods']));

        $this->assertCount(2, $result['entries']);
        $this->assertTrue(collect($result['entries'])->every(fn (array $entry) => (int) $entry['teacher_id'] === $teacher->id));
    }

    public function test_principal_can_export_school_timetable_as_csv(): void
    {
        $school = $this->createSchool();
        $config = $this->createConfig($school['institution']);
        $teacher = $this->createUser($school['institution'], 'teacher', 'csv-teacher@example.com');
        $classroom = Classroom::create([
            'institution_id' => $school['institution']->id,
            'name' => 'Grade CSV',
            'code' => 'CSV',
        ]);
        $subject = $this->createSubject($school['institution'], $classroom, $teacher, 'Csv Math');

        TimetableEntry::create([
            'institution_id' => $school['institution']->id,
            'config_id' => $config->id,
            'academic_year' => $config->academic_year,
            'term' => $config->term,
            'subject_id' => $subject->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id,
            'weekday' => 1,
            'period_number' => 1,
            'start_time' => '08:00:00',
            'end_time' => '08:40:00',
            'entry_type' => 'lesson',
            'version' => 1,
            'is_locked' => false,
        ]);

        $this->actingAs($school['principal'], 'sanctum');

        $response = $this->get('/api/v1/principal/school-timetable?export=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('Csv Math', $response->streamedContent());
    }

    private function createSchool(): array
    {
        $admin = Admin::create([
            'first_name' => 'System',
            'sure_name' => 'Admin',
            'email' => 'rota-admin@example.com',
            'phone_number' => '9000000000',
            'password' => 'secret',
            'role' => 'manager',
        ]);

        $institution = Institution::create([
            'manager_id' => $admin->id,
            'name' => 'Rota Test School',
            'email' => 'rota-school@example.com',
            'phone_number' => '8000000000',
        ]);

        $principal = $this->createUser($institution, 'principal', 'principal-rota@example.com');

        return [
            'institution' => $institution,
            'principal' => $principal,
        ];
    }

    private function createConfig(Institution $institution, string $mode = 'secondary'): SchoolTimetableConfig
    {
        $config = SchoolTimetableConfig::create([
            'institution_id' => $institution->id,
            'academic_year' => '2026-2027',
            'term' => 'first',
            'mode' => $mode,
            'school_start_time' => '08:00:00',
            'school_end_time' => '12:00:00',
            'lesson_duration_minutes' => 40,
            'is_active' => true,
        ]);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $config->workingDays()->create([
                'weekday' => $weekday,
                'is_open' => true,
            ]);
        }

        $config->breakPeriods()->create([
            'weekday' => null,
            'name' => 'Short Break',
            'break_type' => 'break',
            'start_time' => '10:00:00',
            'end_time' => '10:20:00',
        ]);

        return $config;
    }

    private function createClassrooms(Institution $institution): array
    {
        return [
            Classroom::create([
                'institution_id' => $institution->id,
                'name' => 'Grade A',
                'code' => 'GA',
            ]),
            Classroom::create([
                'institution_id' => $institution->id,
                'name' => 'Grade B',
                'code' => 'GB',
            ]),
        ];
    }

    private function createTeachers(Institution $institution): array
    {
        return [
            $this->createUser($institution, 'teacher', 'teacher-a@example.com'),
            $this->createUser($institution, 'teacher', 'teacher-b@example.com'),
        ];
    }

    private function createUser(Institution $institution, string $role, string $email): User
    {
        return User::create([
            'email' => $email,
            'phone_number' => '7' . random_int(100000000, 999999999),
            'password' => 'secret',
            'role' => $role,
            'otp_verified' => true,
            'status' => true,
            'first_name' => ucfirst($role),
            'sur_name' => 'User',
            'institution_id' => $institution->id,
        ]);
    }

    private function createSubject(Institution $institution, Classroom $classroom, ?User $teacher, string $name): Subject
    {
        return Subject::create([
            'institution_id' => $institution->id,
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher?->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '-', $name)),
            'lectures_per_week' => 1,
        ]);
    }

    private function countTeacherConflicts(\Illuminate\Support\Collection $entries): int
    {
        return $entries
            ->groupBy(fn (array $entry) => $entry['teacher_id'] . '-' . $entry['weekday'] . '-' . $entry['start_time'] . '-' . $entry['end_time'])
            ->filter(fn (\Illuminate\Support\Collection $group) => $group->count() > 1)
            ->count();
    }

    private function countClassroomConflicts(\Illuminate\Support\Collection $entries): int
    {
        return $entries
            ->groupBy(fn (array $entry) => $entry['classroom_id'] . '-' . $entry['weekday'] . '-' . $entry['start_time'] . '-' . $entry['end_time'])
            ->filter(fn (\Illuminate\Support\Collection $group) => $group->count() > 1)
            ->count();
    }
}
