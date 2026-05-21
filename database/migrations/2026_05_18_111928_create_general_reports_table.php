<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('general_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('institution_id')->nullable()->constrained('institutions')->cascadeOnDelete();
            $table->string('reported_to_role'); // principal, manager, admin, sub_admin
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('pending'); // pending, resolved
            $table->text('response')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_reports');
    }
};
