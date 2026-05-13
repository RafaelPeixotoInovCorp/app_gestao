<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function teamsKey(): string
    {
        return 'tenant_id';
    }

    public function up(): void
    {
        $teamsKey = $this->teamsKey();
        $rolesTable = config('permission.table_names.roles');
        $mhrTable = config('permission.table_names.model_has_roles');
        $mhpTable = config('permission.table_names.model_has_permissions');
        $rhpTable = config('permission.table_names.role_has_permissions');
        $permTable = config('permission.table_names.permissions');

        if (! Schema::hasColumn($rolesTable, $teamsKey)) {
            Schema::table($rolesTable, function (Blueprint $table): void {
                $table->dropUnique(['name', 'guard_name']);
            });

            Schema::table($rolesTable, function (Blueprint $table) use ($teamsKey): void {
                $table->unsignedBigInteger($teamsKey)->nullable()->after('id');
                $table->foreign($teamsKey)->references('id')->on('tenants')->cascadeOnDelete();
                $table->unique([$teamsKey, 'name', 'guard_name']);
            });
        }

        $templateRoles = DB::table($rolesTable)->whereNull($teamsKey)->orderBy('id')->get();
        $oldAssignments = DB::table($mhrTable)->get();

        $tenantIds = DB::table('tenants')->orderBy('id')->pluck('id');

        $templateIdToNewIds = [];

        if ($templateRoles->isNotEmpty() && $tenantIds->isNotEmpty()) {
            foreach ($tenantIds as $tenantId) {
                foreach ($templateRoles as $tpl) {
                    $newId = DB::table($rolesTable)->insertGetId([
                        $teamsKey => $tenantId,
                        'name' => $tpl->name,
                        'guard_name' => $tpl->guard_name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $templateIdToNewIds[$tenantId][$tpl->id] = $newId;

                    foreach (DB::table($rhpTable)->where('role_id', $tpl->id)->pluck('permission_id') as $pid) {
                        DB::table($rhpTable)->insert([
                            'permission_id' => $pid,
                            'role_id' => $newId,
                        ]);
                    }
                }
            }

            $tplIds = $templateRoles->pluck('id')->all();
            DB::table($rhpTable)->whereIn('role_id', $tplIds)->delete();
            DB::table($rolesTable)->whereIn('id', $tplIds)->delete();
        }

        $newMhrRows = [];

        foreach ($oldAssignments as $row) {
            $tpl = $templateRoles->firstWhere('id', $row->role_id);

            if ($tpl !== null) {
                $userTenantIds = DB::table('tenant_user')
                    ->where('user_id', $row->model_id)
                    ->pluck('tenant_id');

                foreach ($userTenantIds as $tid) {
                    $newRoleId = $templateIdToNewIds[$tid][$tpl->id] ?? null;
                    if ($newRoleId === null) {
                        continue;
                    }
                    $newMhrRows[] = [
                        $teamsKey => $tid,
                        'role_id' => $newRoleId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ];
                }

                continue;
            }

            $scoped = DB::table($rolesTable)
                ->where('id', $row->role_id)
                ->whereNotNull($teamsKey)
                ->first();

            if ($scoped !== null) {
                $newMhrRows[] = [
                    $teamsKey => $scoped->tenant_id,
                    'role_id' => $scoped->id,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ];
            }
        }

        Schema::disableForeignKeyConstraints();
        Schema::drop($mhrTable);
        Schema::create($mhrTable, function (Blueprint $table) use ($teamsKey, $rolesTable): void {
            $table->unsignedBigInteger($teamsKey);
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index([$teamsKey, 'model_id', 'model_type'], 'model_has_roles_team_model_index');
            $table->foreign('role_id')->references('id')->on($rolesTable)->cascadeOnDelete();
            $table->primary([$teamsKey, 'role_id', 'model_id', 'model_type'], 'model_has_roles_team_role_model_primary');
        });
        Schema::enableForeignKeyConstraints();

        foreach (array_chunk($newMhrRows, 200) as $chunk) {
            DB::table($mhrTable)->insert($chunk);
        }

        if (! Schema::hasColumn($mhpTable, $teamsKey)) {
            $existing = DB::table($mhpTable)->get();
            $firstTenantId = DB::table('tenants')->orderBy('id')->value('id');

            Schema::disableForeignKeyConstraints();
            Schema::drop($mhpTable);
            Schema::create($mhpTable, function (Blueprint $table) use ($teamsKey, $permTable): void {
                $table->unsignedBigInteger($teamsKey);
                $table->unsignedBigInteger('permission_id');
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->index([$teamsKey, 'model_id', 'model_type'], 'model_has_permissions_team_model_index');
                $table->foreign('permission_id')->references('id')->on($permTable)->cascadeOnDelete();
                $table->primary([$teamsKey, 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_team_permission_model_primary');
            });
            Schema::enableForeignKeyConstraints();

            $inserts = [];
            foreach ($existing as $row) {
                if ($firstTenantId === null) {
                    break;
                }
                $inserts[] = [
                    $teamsKey => $firstTenantId,
                    'permission_id' => $row->permission_id,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ];
            }
            foreach (array_chunk($inserts, 200) as $chunk) {
                DB::table($mhpTable)->insert($chunk);
            }
        }

        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        //
    }
};
