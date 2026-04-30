<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('access-module', function ($user, string $moduleKey): bool {
            return $user->hasRole('admin') || $user->can("module.{$moduleKey}.view");
        });

        foreach (Modules::ITEMS as $module) {
            Gate::define("module-{$module['key']}", function ($user) use ($module): bool {
                return $user->hasRole('admin') || $user->can("module.{$module['key']}.view");
            });
        }
    }
}
