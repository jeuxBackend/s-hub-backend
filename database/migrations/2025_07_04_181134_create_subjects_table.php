<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('classroom_id')->nullable()->constrained()->onDelete('set null');

            $table->enum('type', ['theory', 'practical'])->default('theory');
            $table->boolean('status')->default(true);

            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->timestamps();

            $table->unique(['classroom_id', 'name']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
