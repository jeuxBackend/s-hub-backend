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
        Schema::table('admins', function (Blueprint $table) {
            $table->string('stripe_connect_account_id')->nullable()->after('fcm_token');
            $table->boolean('stripe_onboarding_completed')->default(false)->after('stripe_connect_account_id');
        });

        Schema::table('student_invoices', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['stripe_connect_account_id', 'stripe_onboarding_completed']);
        });

        Schema::table('student_invoices', function (Blueprint $table) {
            $table->dropColumn('stripe_payment_intent_id');
        });
    }
};
