<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorized_pickups', function (Blueprint $table) {
            // The unique index backs the parent_id foreign key, so it must be
            // replaced with a plain index before it can be dropped.
            $table->index('parent_id');
            $table->dropUnique(['parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->unique('parent_id');
        });
    }
};
