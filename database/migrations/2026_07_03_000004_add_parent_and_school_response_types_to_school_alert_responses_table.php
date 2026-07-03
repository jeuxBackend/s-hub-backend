<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('school_alert_responses', function (Blueprint $table) {
            $table->string('parent_response_type')->nullable()->after('source_role');
            $table->string('school_response_type')->nullable()->after('parent_response_type');
            $table->foreignId('parent_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('school_user_id')->nullable()->after('parent_user_id')->constrained('users')->nullOnDelete();

            $table->index(['school_alert_id', 'parent_response_type'], 'sar_parent_type_idx');
            $table->index(['school_alert_id', 'school_response_type'], 'sar_school_type_idx');
            $table->index(['school_alert_id', 'parent_user_id'], 'sar_parent_user_idx');
            $table->index(['school_alert_id', 'school_user_id'], 'sar_school_user_idx');
        });

        Schema::table('school_alert_responses', function (Blueprint $table) {
            $table->dropColumn('response_type');
        });
    }

    public function down(): void
    {
        Schema::table('school_alert_responses', function (Blueprint $table) {
            $table->dropIndex('sar_parent_type_idx');
            $table->dropIndex('sar_school_type_idx');
            $table->dropIndex('sar_parent_user_idx');
            $table->dropIndex('sar_school_user_idx');
            $table->string('response_type')->nullable()->after('source_role');
        });

        Schema::table('school_alert_responses', function (Blueprint $table) {
            $table->dropColumn(['parent_response_type', 'school_response_type', 'parent_user_id', 'school_user_id']);
        });
    }
};
