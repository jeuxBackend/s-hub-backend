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
        Schema::create('classroom_teachers', function (Blueprint $table) {
        $table->id();
        $table->foreignId('classroom_id')->constrained()->onDelete('cascade');
        $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
        $table->enum('term', ['first', 'second', 'third', 'final'])->nullable();
        $table->string('year')->nullable(); // e.g., "2025"
        $table->string('section')->nullable(); // e.g., "A", "Blue", etc.
        $table->timestamps();
          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classroom_teachers');
    }
};
