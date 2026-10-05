<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['stamp', 'watermark']);
            $table->enum('asset_format', ['text', 'image']);
            $table->text('text_value')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Exactly one stamp and one watermark per school.
            $table->unique(['institution_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_assets');
    }
};
