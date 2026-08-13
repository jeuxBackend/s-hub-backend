<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->timestamp('checked_out_at')->nullable()->after('status');
            $table->string('checkout_type')->nullable()->after('checked_out_at'); // manual, auto
        });
    }

    public function down(): void
    {
        Schema::table('teacher_attendances', function (Blueprint $table) {
            $table->dropColumn(['checked_out_at', 'checkout_type']);
        });
    }
};
