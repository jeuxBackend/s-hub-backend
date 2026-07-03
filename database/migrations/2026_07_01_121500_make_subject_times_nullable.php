<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subjects MODIFY start_time TIME NULL');
        DB::statement('ALTER TABLE subjects MODIFY end_time TIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subjects MODIFY start_time TIME NOT NULL');
        DB::statement('ALTER TABLE subjects MODIFY end_time TIME NOT NULL');
    }
};
