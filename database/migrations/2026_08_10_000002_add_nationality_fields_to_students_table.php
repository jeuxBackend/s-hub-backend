<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('nationality')->nullable()->after('religion');
            $table->string('country_of_birth')->nullable()->after('nationality');
            $table->string('primary_language')->nullable()->after('country_of_birth');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['nationality', 'country_of_birth', 'primary_language']);
        });
    }
};
