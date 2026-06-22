<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->constrained('students', 'id')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropColumn('student_id');
        });
    }
};