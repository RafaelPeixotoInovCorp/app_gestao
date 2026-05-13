<?php

namespace App\Providers;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantPolicy;
use App\Services\TenantContext;
use App\Support\Modules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Tenant::class, TenantPolicy::class);

        Gate::define('access-module', function ($user, string $moduleKey): bool {
            return $user->hasRole('admin') || $user->can("module.{$moduleKey}.view");
        });

        Gate::define('manage-subscription', function (User $user): bool {
            if (app(TenantContext::class)->id() === null) {
                return false;
            }

            return $user->hasRole('admin');
        });

        foreach (Modules::ITEMS as $module) {
            Gate::define("module-{$module['key']}", function ($user) use ($module): bool {
                return $user->hasRole('admin') || $user->can("module.{$module['key']}.view");
            });
        }
    }
}
