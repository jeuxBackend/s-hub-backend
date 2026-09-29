<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            // The original constraint predates config_id/version and treats
            // every config/term/academic year as one global slot pool,
            // wrongly blocking a new config from reusing a slot an old
            // (or locked) config already occupies for the same subject.
            $table->dropUnique('timetable_subject_slot_unique');

            $table->unique(
                ['config_id', 'subject_id', 'weekday', 'start_time', 'end_time'],
                'timetable_subject_slot_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_entries', function (Blueprint $table) {
            $table->dropUnique('timetable_subject_slot_unique');

            $table->unique(
                ['subject_id', 'weekday', 'start_time', 'end_time'],
                'timetable_subject_slot_unique'
            );
        });
    }
};
