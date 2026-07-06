<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("UPDATE subjects SET start_time = '00:00:00'");
        DB::statement("UPDATE subjects SET end_time = '00:00:00'");

        DB::statement('ALTER TABLE subjects MODIFY start_time TIME NULL');
        DB::statement('ALTER TABLE subjects MODIFY end_time TIME NULL');

        DB::statement("UPDATE subjects SET start_time = NULL");
        DB::statement("UPDATE subjects SET end_time = NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE subjects SET start_time = '00:00:00' WHERE start_time IS NULL");
        DB::statement("UPDATE subjects SET end_time = '00:00:00' WHERE end_time IS NULL");

        DB::statement('ALTER TABLE subjects MODIFY start_time TIME NOT NULL');
        DB::statement('ALTER TABLE subjects MODIFY end_time TIME NOT NULL');
    }
};

