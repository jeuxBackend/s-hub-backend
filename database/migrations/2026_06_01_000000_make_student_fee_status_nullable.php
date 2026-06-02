<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'pgsql') {
            DB::statement("ALTER TABLE student_fees MODIFY COLUMN status ENUM('paid','unpaid','partial') NULL DEFAULT NULL");
        } elseif ($driver === 'sqlite') {
            Schema::table('student_fees', function (Blueprint $table) {
                $table->string('status')->nullable()->default(null)->change();
            });
        } else {
            throw new \RuntimeException('Unsupported database driver: ' . $driver);
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'pgsql') {
            DB::statement("ALTER TABLE student_fees MODIFY COLUMN status ENUM('paid','unpaid','partial') NOT NULL DEFAULT 'unpaid'");
        } elseif ($driver === 'sqlite') {
            Schema::table('student_fees', function (Blueprint $table) {
                $table->string('status')->nullable(false)->default('unpaid')->change();
            });
        } else {
            throw new \RuntimeException('Unsupported database driver: ' . $driver);
        }
    }
};
