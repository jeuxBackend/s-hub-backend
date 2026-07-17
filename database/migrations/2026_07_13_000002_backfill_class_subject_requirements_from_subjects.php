<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            !Schema::hasTable('class_subject_requirements') ||
            !Schema::hasTable('subjects') ||
            !Schema::hasColumn('subjects', 'teacher_id') ||
            !Schema::hasColumn('subjects', 'lectures_per_week')
        ) {
            return;
        }

        DB::table('class_subject_requirements')->insertUsing(
            [
                'institution_id',
                'classroom_id',
                'subject_id',
                'teacher_id',
                'lessons_per_week',
                'double_period_allowed',
                'priority',
                'is_active',
                'created_at',
                'updated_at',
            ],
            DB::table('subjects as s')
                ->join('classrooms as c', 'c.id', '=', 's.classroom_id')
                ->leftJoin('users as u', 'u.id', '=', 's.teacher_id')
                ->leftJoin('class_subject_requirements as csr', function ($join) {
                    $join->on('csr.classroom_id', '=', 's.classroom_id')
                        ->on('csr.subject_id', '=', 's.id');
                })
                ->whereNull('csr.id')
                ->whereNotNull('s.classroom_id')
                ->selectRaw('
                    s.institution_id,
                    s.classroom_id,
                    s.id as subject_id,
                    u.id as teacher_id,
                    CASE WHEN COALESCE(s.lectures_per_week, 1) < 1 THEN 1 ELSE COALESCE(s.lectures_per_week, 1) END as lessons_per_week,
                    0 as double_period_allowed,
                    1 as priority,
                    1 as is_active,
                    NOW() as created_at,
                    NOW() as updated_at
                ')
        );
    }

    public function down(): void
    {
        // Intentional no-op: these rows become production scheduling data after migration.
    }
};
