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
        Schema::create('students', function (Blueprint $table) {
            $table->id();

            $table->string('profile_picture')->nullable();
            $table->string('first_name');
            $table->string('sur_name')->nullable();
            $table->string('student_phone_number')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->integer('age')->nullable();
            $table->string('religion')->nullable();
            $table->text('address')->nullable();


            // Academic Info
            $table->string('term')->nullable(); // e.g., Spring 2025
            $table->foreignId('classroom_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('institution_id')->nullable()->constrained()->onDelete('set null');

            // Guardian & Created By
            $table->foreignId('guardian_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            // System
            $table->string('registration_number')->unique();
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
