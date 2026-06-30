<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('school_alert_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_alert_id')->constrained('school_alerts')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('source_role');
            $table->string('response_type');
            $table->longText('note')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['school_alert_id', 'user_id']);
            $table->index(['school_alert_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_alert_responses');
    }
};
