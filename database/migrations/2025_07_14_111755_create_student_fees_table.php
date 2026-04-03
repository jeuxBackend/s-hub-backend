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
        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->string('term')->nullable(); // e.g. Spring 2025

          
            $table->decimal('tuition_fee', 10, 2)->nullable();
            $table->decimal('uniform_fee', 10, 2)->nullable();
            $table->decimal('meals_fee', 10, 2)->nullable();
            $table->decimal('books_fee', 10, 2)->nullable();
            $table->decimal('other_fee', 10, 2)->nullable();

           
            $table->decimal('paid_amount', 10, 2)->nullable()->default(0);
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();

          
            $table->enum('status', ['paid', 'unpaid', 'partial'])->default('unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_fees');
    }
};
