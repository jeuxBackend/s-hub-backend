<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('institution_id')->constrained('institutions')->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('present'); // present, absent, etc
            $table->boolean('is_remote')->default(false); // flag to indicate if it was marked remotely
            $table->timestamps();

            // A teacher can only mark attendance once per subject per day
            $table->unique(['teacher_id', 'subject_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendances');
    }
};
