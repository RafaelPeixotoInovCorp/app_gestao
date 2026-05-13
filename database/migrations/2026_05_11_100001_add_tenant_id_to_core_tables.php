<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('module_records', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('calendar_tasks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        $defaultTenantId = null;
        if (! DB::table('tenants')->where('slug', 'organizacao-principal')->exists()) {
            $defaultTenantId = DB::table('tenants')->insertGetId([
                'name' => 'Organização principal',
                'slug' => 'organizacao-principal',
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $defaultTenantId = (int) DB::table('tenants')->where('slug', 'organizacao-principal')->value('id');
        }

        DB::table('entities')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        DB::table('module_records')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);
        DB::table('calendar_tasks')->whereNull('tenant_id')->update(['tenant_id' => $defaultTenantId]);

        $userIds = DB::table('users')->pluck('id');
        foreach ($userIds as $userId) {
            $exists = DB::table('tenant_user')
                ->where('tenant_id', $defaultTenantId)
                ->where('user_id', $userId)
                ->exists();
            if (! $exists) {
                DB::table('tenant_user')->insert([
                    'tenant_id' => $defaultTenantId,
                    'user_id' => $userId,
                    'role' => 'owner',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('users')->where('id', $userId)->whereNull('last_tenant_id')->update(['last_tenant_id' => $defaultTenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('calendar_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('module_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
