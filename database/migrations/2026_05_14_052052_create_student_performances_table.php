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
        Schema::create('student_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('total_mark', 5, 2)->default(0);
            $table->decimal('obtained_mark', 5, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->string('result_sheet')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_performances');
    }
};
