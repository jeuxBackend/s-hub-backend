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
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->string('payment_type')->nullable()->after('payment_method');
            $table->string('document')->nullable()->after('payment_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'document']);
        });
    }
};
