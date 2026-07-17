<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->foreignId('config_id')->nullable()->after('institution_id')->constrained('school_timetable_configs')->nullOnDelete();
            $table->string('academic_year')->nullable()->after('config_id');
            $table->string('term')->nullable()->after('academic_year');
            $table->unsignedTinyInteger('period_number')->nullable()->after('weekday');
            $table->string('entry_type')->default('lesson')->after('end_time');
            $table->unsignedInteger('version')->default(1)->after('entry_type');
            $table->boolean('is_locked')->default(false)->after('version');

            $table->index(['institution_id', 'academic_year', 'term'], 'timetable_entries_term_lookup_index');
            $table->index(['config_id', 'weekday', 'period_number'], 'timetable_entries_config_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropIndex('timetable_entries_term_lookup_index');
            $table->dropIndex('timetable_entries_config_period_index');
            $table->dropConstrainedForeignId('config_id');
            $table->dropColumn([
                'academic_year',
                'term',
                'period_number',
                'entry_type',
                'version',
                'is_locked',
            ]);
        });
    }
};
