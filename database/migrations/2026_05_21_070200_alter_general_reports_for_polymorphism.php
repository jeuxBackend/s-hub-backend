<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_reports', function (Blueprint $table) {
            $table->dropForeign(['reporter_id']);
            $table->dropForeign(['resolved_by_id']);

            $table->string('reporter_type')->after('id')->default('App\\\Models\\\User');
            $table->string('resolved_by_type')->nullable()->after('response');
        });
    }

    public function down(): void
    {
        Schema::table('general_reports', function (Blueprint $table) {
            $table->dropColumn(['reporter_type', 'resolved_by_type']);
            $table->foreign('reporter_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('resolved_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
