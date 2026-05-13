<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantOnboardingBootstrap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PersonalTenantRegistrar
{
    public static function assign(User $user, ?Request $request = null): Tenant
    {
        return DB::transaction(function () use ($user, $request): Tenant {
            $base = Str::slug(Str::limit($user->name, 40, ''));
            if ($base === '') {
                $base = Str::slug(Str::before($user->email, '@')) ?: 'organizacao';
            }
            $slug = self::uniqueSlug(Str::lower($base));

            $tenant = Tenant::query()->create([
                'name' => $user->name.' — Organização',
                'slug' => $slug,
                'settings' => TenantOnboardingBootstrap::initialSettings(),
            ]);

            $tenant->users()->attach($user->id, ['role' => 'owner']);
            $user->forceFill(['last_tenant_id' => $tenant->id])->save();

            TenantOnboardingBootstrap::finalizeNewTenant($tenant, $user, $request);

            return $tenant;
        });
    }

    private static function uniqueSlug(string $base): string
    {
        $candidate = $base;
        $i = 0;
        while (Tenant::query()->where('slug', $candidate)->exists()) {
            $i++;
            $candidate = $base.'-'.$i;
        }

        return $candidate;
    }
}
