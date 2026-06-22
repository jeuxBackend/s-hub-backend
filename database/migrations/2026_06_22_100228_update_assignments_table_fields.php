<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Add new columns
            $table->text('assignment_text')->nullable()->after('title');
            $table->dateTime('submission_end_date')->nullable()->after('status');
            $table->dateTime('assignment_date')->nullable()->after('submission_end_date');
            
            // Remove old columns if they exist
            if (Schema::hasColumn('assignments', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('assignments', 'due_date')) {
                $table->dropColumn('due_date');
            }
            if (Schema::hasColumn('assignments', 'max_score')) {
                $table->dropColumn('max_score');
            }
            if (Schema::hasColumn('assignments', 'instructions')) {
                $table->dropColumn('instructions');
            }
        });
    }

    public function down()
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Reverse the changes
            $table->text('description')->nullable()->after('title');
            $table->dateTime('due_date')->nullable()->after('status');
            $table->integer('max_score')->default(0)->after('due_date');
            $table->text('instructions')->nullable()->after('max_score');
            
            // Remove new columns
            $table->dropColumn(['assignment_text', 'submission_end_date', 'assignment_date']);
        });
    }
};