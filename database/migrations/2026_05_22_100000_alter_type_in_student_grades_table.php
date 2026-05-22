<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First we drop the column if it's an enum, since modifying enum directly has issues in doctrine/dbal sometimes.
        // Wait, for MySQL, we can just change to string. Let's do string.
        Schema::table('student_grades', function (Blueprint $table) {
            $table->string('type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_grades', function (Blueprint $table) {
            // It's hard to revert back to enum exactly if data exists, but we can try
            $table->enum('type', ['exam', 'assignment', 'quiz'])->nullable()->change();
        });
    }
};
