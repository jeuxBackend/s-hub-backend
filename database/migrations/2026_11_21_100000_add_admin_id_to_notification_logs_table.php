<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            // In-app notifications for admin-side accounts (admin, sub_admin,
            // sub_sub_admin, manager, sub_manager). user_id stays null for these.
            $table->foreignId('admin_id')->nullable()->after('user_id')
                ->constrained('admins')->cascadeOnDelete();
            $table->index(['admin_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropIndex(['admin_id', 'is_read']);
            $table->dropConstrainedForeignId('admin_id');
        });
    }
};
