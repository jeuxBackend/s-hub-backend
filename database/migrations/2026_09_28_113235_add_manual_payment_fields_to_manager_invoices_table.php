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
        Schema::table('manager_invoices', function (Blueprint $table) {
            $table->text('payment_note')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('paid_submitted_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('manager_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn(['payment_note', 'payment_method', 'payment_proof', 'paid_submitted_at', 'confirmed_at']);
        });
    }
};
