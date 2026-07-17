<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_timetable_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('academic_year')->nullable();
            $table->string('term')->nullable();
            $table->string('mode')->default('secondary');
            $table->time('school_start_time');
            $table->time('school_end_time');
            $table->unsignedSmallInteger('lesson_duration_minutes');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'is_active']);
        });

        Schema::create('school_timetable_working_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('school_timetable_configs')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->boolean('is_open')->default(true);
            $table->timestamps();

            $table->unique(['config_id', 'weekday'], 'school_timetable_working_days_unique');
        });

        Schema::create('school_timetable_break_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('school_timetable_configs')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->string('name');
            $table->string('break_type')->default('break');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('teacher_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('config_id')->nullable()->constrained('school_timetable_configs')->nullOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('availability_type')->default('available');
            $table->timestamps();

            $table->index(['institution_id', 'teacher_id', 'weekday'], 'teacher_availabilities_lookup_index');
        });

        Schema::create('class_subject_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('lessons_per_week');
            $table->boolean('double_period_allowed')->default(false);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['classroom_id', 'subject_id'], 'class_subject_requirements_unique');
            $table->index(['institution_id', 'classroom_id'], 'class_subject_requirements_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_subject_requirements');
        Schema::dropIfExists('teacher_availabilities');
        Schema::dropIfExists('school_timetable_break_periods');
        Schema::dropIfExists('school_timetable_working_days');
        Schema::dropIfExists('school_timetable_configs');
    }
};
