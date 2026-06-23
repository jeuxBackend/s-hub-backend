<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_preregistrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->foreignId('guardian_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('current_classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->foreignId('target_classroom_id')->constrained('classrooms')->restrictOnDelete();
            $table->string('academic_year');
            $table->enum('status', ['submitted', 'approved', 'rejected'])->default('submitted');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['student_id', 'current_classroom_id', 'target_classroom_id', 'academic_year'],
                'student_preregistrations_unique_batch'
            );
            $table->index(['guardian_id', 'status']);
            $table->index(['current_classroom_id', 'status']);
            $table->index(['target_classroom_id', 'status']);
            $table->index(['academic_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_preregistrations');
    }
};
