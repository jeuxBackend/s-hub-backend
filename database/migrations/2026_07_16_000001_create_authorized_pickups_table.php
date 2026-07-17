<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('authorized_pickups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_number');
            $table->text('address');
            $table->timestamps();

            $table->unique('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorized_pickups');
    }
};
