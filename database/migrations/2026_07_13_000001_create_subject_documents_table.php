<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained('classrooms')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('document_type', ['yearly_syllabus', 'study_material']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('term')->nullable();
            $table->timestamps();

            $table->index(['institution_id', 'document_type'], 'subject_documents_type_lookup_index');
            $table->index(['teacher_id', 'classroom_id', 'subject_id'], 'subject_documents_teacher_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_documents');
    }
};
