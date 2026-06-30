<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('first_name');
            $table->string('sur_name');
            $table->string('email')->unique();
            $table->string('phone_number')->unique();
            $table->text('address')->nullable();
            $table->string('relation_with_parent');
            $table->string('profile_picture')->nullable();
            $table->string('password');
            $table->timestamps();

            $table->index('parent_id');
            $table->foreign('parent_id', 'family_members_parent_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_members');
    }
};
