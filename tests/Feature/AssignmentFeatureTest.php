<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class AssignmentFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_create_assignment()
    {
        // Create a teacher user
        $teacher = User::factory()->create([
            'role' => 'teacher'
        ]);

        // Create a classroom and subject
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id
        ]);

        // Authenticate as the teacher
        $this->actingAs($teacher, 'sanctum');

        // Make the request to create an assignment
        $response = $this->postJson('/api/v1/assignments', [
            'title' => 'Test Assignment',
            'description' => 'This is a test assignment',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'status' => 'assigned',
            'due_date' => '2026-12-31',
            'max_score' => 100
        ]);

        // Assert the response
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'title',
                         'description',
                         'status',
                         'due_date',
                         'max_score'
                     ]
                 ]);

        // Assert the assignment was created in the database
        $this->assertDatabaseHas('assignments', [
            'title' => 'Test Assignment',
            'description' => 'This is a test assignment',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'assigned'
        ]);
    }

    public function test_parent_can_view_assignments_for_their_children()
    {
        // Create a parent user
        $parent = User::factory()->create([
            'role' => 'parent'
        ]);

        // Create a student linked to the parent
        $student = Student::factory()->create([
            'guardian_id' => $parent->id
        ]);

        // Create a classroom and assign the student to it
        $classroom = Classroom::factory()->create();
        $student->update(['classroom_id' => $classroom->id]);

        // Create a subject for the classroom
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id
        ]);

        // Create an assignment for the classroom and subject
        $assignment = Assignment::create([
            'title' => 'Test Assignment for Parent',
            'description' => 'This assignment is visible to parent',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => User::factory()->create(['role' => 'teacher'])->id,
            'status' => 'assigned'
        ]);

        // Authenticate as the parent
        $this->actingAs($parent, 'sanctum');

        // Request assignments for the parent
        $response = $this->getJson('/api/v1/parent/assignments');

        // Assert the response includes the assignment
        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'title' => 'Test Assignment for Parent'
                 ]);
    }

    public function test_teacher_can_update_assignment()
    {
        // Create a teacher user
        $teacher = User::factory()->create([
            'role' => 'teacher'
        ]);

        // Create a classroom and subject
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id
        ]);

        // Create an assignment owned by the teacher
        $assignment = Assignment::create([
            'title' => 'Original Assignment',
            'description' => 'Original description',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'draft'
        ]);

        // Authenticate as the teacher
        $this->actingAs($teacher, 'sanctum');

        // Update the assignment
        $response = $this->putJson("/api/v1/assignments/{$assignment->id}", [
            'title' => 'Updated Assignment',
            'status' => 'assigned'
        ]);

        // Assert the response
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message',
                     'data' => [
                         'id',
                         'title',
                         'status'
                     ]
                 ]);

        // Assert the assignment was updated in the database
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'title' => 'Updated Assignment',
            'status' => 'assigned'
        ]);
    }

    public function test_teacher_can_delete_assignment()
    {
        // Create a teacher user
        $teacher = User::factory()->create([
            'role' => 'teacher'
        ]);

        // Create a classroom and subject
        $classroom = Classroom::factory()->create();
        $subject = Subject::factory()->create([
            'classroom_id' => $classroom->id,
            'teacher_id' => $teacher->id
        ]);

        // Create an assignment owned by the teacher
        $assignment = Assignment::create([
            'title' => 'Assignment to Delete',
            'description' => 'Will be deleted',
            'classroom_id' => $classroom->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'draft'
        ]);

        // Authenticate as the teacher
        $this->actingAs($teacher, 'sanctum');

        // Delete the assignment
        $response = $this->deleteJson("/api/v1/assignments/{$assignment->id}");

        // Assert the response
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'message'
                 ]);

        // Assert the assignment was deleted from the database
        $this->assertSoftDeleted('assignments', [
            'id' => $assignment->id
        ]);
    }
}