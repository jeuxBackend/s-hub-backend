<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_result_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->string('term');
            $table->string('conduct')->nullable();
            $table->unsignedSmallInteger('possible_attendance')->nullable();
            $table->unsignedSmallInteger('times_absent')->nullable();
            $table->unsignedSmallInteger('times_late')->nullable();
            $table->text('teacher_remarks')->nullable();
            $table->text('head_teacher_remarks')->nullable();
            $table->date('next_term_begins')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'classroom_id', 'term'], 'student_result_remarks_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_result_remarks');
    }
};
