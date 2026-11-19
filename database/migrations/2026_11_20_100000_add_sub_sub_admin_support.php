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
        // admins.role is a DB enum — add the new 'sub_sub_admin' value.
        if (DB::getDriverName() === 'mysql') {
            $values = implode(',', array_map(fn ($v) => "'" . $v . "'", AdminRole::values()));
            DB::statement("ALTER TABLE admins MODIFY role ENUM($values) NOT NULL DEFAULT 'sub_admin'");
        }

        Schema::table('admins', function (Blueprint $table) {
            // The sub-admin who created this sub-sub-admin (null for every other role).
            $table->foreignId('parent_id')->nullable()->after('manager_id')
                ->constrained('admins')->cascadeOnDelete();
        });

        // Categories, Invoices and Academic Analytics become real permissions.
        // Grant them to existing sub-admins that could already reach those
        // screens, so nobody loses access they have today.
        DB::table('admins')->where('role', AdminRole::SubAdmin->value)->orderBy('id')->each(function ($admin) {
            $perms = json_decode($admin->permissions ?? '[]', true);
            if (!is_array($perms)) {
                return;
            }

            $new = $perms;
            if (in_array('Schools', $perms, true)) {
                $new[] = 'Categories';
            }
            if (in_array('Managers', $perms, true)) {
                $new[] = 'Invoices';
            }
            if (in_array('Students', $perms, true) || in_array('Dashboard', $perms, true)) {
                $new[] = 'AcademicAnalytics';
            }
            $new = array_values(array_unique($new));

            if ($new !== $perms) {
                DB::table('admins')->where('id', $admin->id)->update(['permissions' => json_encode($new)]);
            }
        });
    }

    public function down(): void
    {
        DB::table('admins')->where('role', 'sub_sub_admin')->delete();

        Schema::table('admins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE admins MODIFY role ENUM('admin','sub_admin','manager','sub_manager') NOT NULL DEFAULT 'sub_admin'");
        }
    }
};
