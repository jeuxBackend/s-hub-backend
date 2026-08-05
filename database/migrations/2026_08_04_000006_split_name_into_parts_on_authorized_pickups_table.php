<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('parent_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('sur_name')->nullable()->after('last_name');
        });

        DB::table('authorized_pickups')->select('id', 'name')->orderBy('id')->each(function ($pickup) {
            $parts = preg_split('/\s+/', trim((string) $pickup->name), 2);

            DB::table('authorized_pickups')
                ->where('id', $pickup->id)
                ->update([
                    'first_name' => $parts[0] !== '' ? $parts[0] : null,
                    'sur_name' => $parts[1] ?? null,
                ]);
        });

        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->string('name')->nullable()->after('parent_id');
        });

        DB::table('authorized_pickups')->select('id', 'first_name', 'last_name', 'sur_name')->orderBy('id')->each(function ($pickup) {
            $name = trim(collect([$pickup->first_name, $pickup->last_name, $pickup->sur_name])->filter()->implode(' '));

            DB::table('authorized_pickups')
                ->where('id', $pickup->id)
                ->update(['name' => $name !== '' ? $name : null]);
        });

        Schema::table('authorized_pickups', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropColumn(['first_name', 'last_name', 'sur_name']);
        });
    }
};
