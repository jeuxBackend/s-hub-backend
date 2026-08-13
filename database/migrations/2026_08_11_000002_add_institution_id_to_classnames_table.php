<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classnames', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->after('id')->constrained('institutions')->cascadeOnDelete();
        });

        Schema::table('classnames', function (Blueprint $table) {
            $table->dropUnique('classnames_name_unique');
        });

        $this->backfillPerInstitution();

        Schema::table('classnames', function (Blueprint $table) {
            $table->unique(['institution_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('classnames', function (Blueprint $table) {
            $table->dropUnique(['institution_id', 'name']);
        });

        // Collapse back down to one global row per distinct name.
        DB::table('classnames')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name')
            ->each(function ($name) {
                $keepId = DB::table('classnames')->where('name', $name)->min('id');
                DB::table('classnames')->where('name', $name)->where('id', '!=', $keepId)->delete();
            });

        Schema::table('classnames', function (Blueprint $table) {
            $table->dropForeign(['institution_id']);
            $table->dropColumn('institution_id');
        });

        Schema::table('classnames', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * The 14 existing global class names get assigned to the first institution,
     * then copied into every other existing institution so nothing loses data.
     */
    private function backfillPerInstitution(): void
    {
        $institutionIds = DB::table('institutions')->orderBy('id')->pluck('id');

        if ($institutionIds->isEmpty()) {
            return;
        }

        $firstInstitutionId = $institutionIds->first();

        DB::table('classnames')
            ->whereNull('institution_id')
            ->update(['institution_id' => $firstInstitutionId]);

        $baseNames = DB::table('classnames')
            ->where('institution_id', $firstInstitutionId)
            ->pluck('name');

        $now = now();

        foreach ($institutionIds->slice(1) as $institutionId) {
            foreach ($baseNames as $name) {
                DB::table('classnames')->insert([
                    'institution_id' => $institutionId,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
