<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->string('relationship')->nullable()->after('sur_name');
        });
    }

    public function down(): void
    {
        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->dropColumn('relationship');
        });
    }
};
