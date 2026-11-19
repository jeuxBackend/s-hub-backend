<?php

use App\Enums\AdminRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // admins.role is a DB enum — add the new 'sub_manager' value.
        if (DB::getDriverName() === 'mysql') {
            $values = implode(',', array_map(fn ($v) => "'" . $v . "'", AdminRole::values()));
            DB::statement("ALTER TABLE admins MODIFY role ENUM($values) NOT NULL DEFAULT 'sub_admin'");
        }

        Schema::table('admins', function (Blueprint $table) {
            // The manager who created this sub-manager (null for every other role).
            $table->foreignId('manager_id')->nullable()->after('role')
                ->constrained('admins')->cascadeOnDelete();
        });

        Schema::table('institutions', function (Blueprint $table) {
            // The one sub-manager (if any) this school is assigned to.
            $table->foreignId('sub_manager_id')->nullable()->after('subadmin_id')
                ->constrained('admins')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sub_manager_id');
        });

        Schema::table('admins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::table('admins')->where('role', 'sub_manager')->delete();
            DB::statement("ALTER TABLE admins MODIFY role ENUM('admin','sub_admin','manager') NOT NULL DEFAULT 'sub_admin'");
        }
    }
};
