<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manager_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->constrained('admins')->cascadeOnDelete();
            $table->string('invoice_number')->nullable();
            $table->decimal('number_of_instutes', 10, 2)->nullable();
            $table->decimal('price_per_instute', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manager_invoices');
    }
};
