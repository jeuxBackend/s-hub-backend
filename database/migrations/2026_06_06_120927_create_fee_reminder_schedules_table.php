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
        Schema::create('fee_reminder_schedules', function (Blueprint $table) {
            $table->id();
            $table->time('notification_time')->comment('Time to send notifications (e.g., 09:00:00)');
            $table->json('days_of_week')->nullable()->comment('Days of week to send [1,2,3,4,5] where 1=Monday, 7=Sunday');
            $table->string('title', 255)->default('Tuition Fee Payment Reminder')->comment('Notification title');
            $table->text('message')->nullable()->comment('Notification message');
            $table->boolean('is_enabled')->default(false)->comment('Whether scheduled notifications are enabled');
            $table->timestamp('last_sent_at')->nullable()->comment('Last time notifications were sent');
            $table->foreignId('institution_id')->constrained()->onDelete('cascade')->comment('Institution this schedule belongs to');
            $table->timestamps();
            
            $table->index(['institution_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_reminder_schedules');
    }
};
