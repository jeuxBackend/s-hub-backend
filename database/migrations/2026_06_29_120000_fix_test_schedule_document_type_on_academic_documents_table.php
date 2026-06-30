<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE academic_documents
            MODIFY document_type ENUM('exam_schedule', 'text_schedule', 'test_schedule', 'academic_transcript') NOT NULL
        ");

        DB::table('academic_documents')
            ->where('document_type', 'text_schedule')
            ->update(['document_type' => 'test_schedule']);

        DB::statement("
            ALTER TABLE academic_documents
            MODIFY document_type ENUM('exam_schedule', 'test_schedule', 'academic_transcript') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE academic_documents
            MODIFY document_type ENUM('exam_schedule', 'text_schedule', 'test_schedule', 'academic_transcript') NOT NULL
        ");

        DB::table('academic_documents')
            ->where('document_type', 'test_schedule')
            ->update(['document_type' => 'text_schedule']);

        DB::statement("
            ALTER TABLE academic_documents
            MODIFY document_type ENUM('exam_schedule', 'text_schedule', 'academic_transcript') NOT NULL
        ");
    }
};
