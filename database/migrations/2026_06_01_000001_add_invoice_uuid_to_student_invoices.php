<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->string('invoice_uuid', 36)->nullable()->after('reference_no');
        });

        DB::table('student_invoices')->whereNull('invoice_uuid')->orderBy('id')->chunk(100, function ($invoices) {
            foreach ($invoices as $invoice) {
                DB::table('student_invoices')
                    ->where('id', $invoice->id)
                    ->update(['invoice_uuid' => Str::uuid()->toString()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_uuid');
        });
    }
};
