<?php

use App\Models\Institution;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_histories', function (Blueprint $table) {
            $table->id();
            $table->morphs('statusable');
            $table->boolean('status');
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['statusable_type', 'changed_at']);
        });

        $this->backfill('users', User::class, 'status');
        $this->backfill('students', Student::class, 'status');
        $this->backfill('institutions', Institution::class, 'is_blocked', invert: true);
    }

    /**
     * Seed one history row per existing row, using its current status as of
     * its own created_at — the best available approximation for data that
     * predates this audit trail.
     */
    private function backfill(string $table, string $modelClass, string $statusColumn, bool $invert = false): void
    {
        $now = now();

        DB::table($table)
            ->select('id', $statusColumn, 'created_at')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($modelClass, $statusColumn, $invert, $now) {
                $insert = $rows->map(function ($row) use ($modelClass, $statusColumn, $invert, $now) {
                    $rawStatus = (bool) $row->{$statusColumn};

                    return [
                        'statusable_type' => $modelClass,
                        'statusable_id' => $row->id,
                        'status' => $invert ? !$rawStatus : $rawStatus,
                        'changed_at' => $row->created_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                if (!empty($insert)) {
                    DB::table('status_histories')->insert($insert);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
    }
};
