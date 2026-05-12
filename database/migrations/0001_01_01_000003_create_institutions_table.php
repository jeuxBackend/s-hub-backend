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
        Schema::create('institutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->constrained('admins')->onDelete('cascade');
            $table->foreignId('subadmin_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('name');
            $table->string('slogan')->nullable();
            $table->string('logo')->nullable();
            $table->string('academic_year')->nullable();
            $table->string('examination_system')->nullable();
            $table->string('physical_address')->nullable();
            $table->string('region')->nullable();
            $table->json('subjects')->nullable();
            $table->string('email')->unique();
            $table->string('alternate_email')->nullable();
            $table->string('phone_number')->unique();
            $table->string('alternate_phone')->nullable();
            $table->string('telephone')->nullable();
            $table->boolean('email_verified')->default(false);
            $table->boolean('phone_verified')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('institutions');
    }
};
