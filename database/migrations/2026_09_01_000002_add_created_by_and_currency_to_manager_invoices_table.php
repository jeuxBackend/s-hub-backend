<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manager_invoices', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('manager_id')->constrained('admins')->nullOnDelete();
            $table->string('currency', 3)->default('USD')->after('price_per_instute');
        });
    }

    public function down(): void
    {
        Schema::table('manager_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('currency');
        });
    }
};
