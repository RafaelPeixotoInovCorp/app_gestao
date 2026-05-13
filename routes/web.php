<?php

use App\Http\Controllers\CountrySettingsController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TenantAppendController;
use App\Http\Controllers\TenantDestroyController;
use App\Http\Controllers\TenantOnboardingController;
use App\Http\Controllers\TenantSetupWizardController;
use App\Http\Controllers\TenantStoreController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\UserAdminController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

Route::get('/__cursor-check', function () {
    return response()->json([
        'project' => 'desenvolvimento-de-aplica-o-de-gest-o',
        'ok' => true,
    ]);
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/organizacoes/nova', [TenantOnboardingController::class, 'create'])->name('tenants.create');
    Route::post('/organizacoes/onboarding', [TenantOnboardingController::class, 'store'])->name('tenants.onboarding.store');
});

Route::get('/dashboard', [ModuleController::class, 'index'])
    ->middleware(['auth', 'verified', 'active', 'tenant', 'subscription'])
    ->name('dashboard');

Route::middleware(['auth', 'verified', 'active', 'tenant', 'subscription'])->group(function (): void {
    Route::get('/organizacao/subscricao', [SubscriptionController::class, 'dashboard'])->name('subscription.dashboard');
    Route::post('/organizacao/subscricao/plano', [SubscriptionController::class, 'changePlan'])->name('subscription.change-plan');
    Route::post('/organizacao/subscricao/cancelar', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

    Route::post('/organizacao/atual', [TenantSwitchController::class, 'update'])->name('tenants.switch');
    Route::get('/organizacoes/adicionar', [TenantAppendController::class, 'create'])->name('tenants.append.create');
    Route::post('/organizacoes', TenantStoreController::class)->name('tenants.store');
    Route::delete('/organizacoes/{tenant}', TenantDestroyController::class)->name('tenants.destroy');

    Route::get('/organizacao/configuracao-inicial', [TenantSetupWizardController::class, 'edit'])->name('tenants.setup.wizard');
    Route::patch('/organizacao/configuracao-inicial/identidade', [TenantSetupWizardController::class, 'updateBranding'])->name('tenants.setup.branding');
    Route::post('/organizacao/configuracao-inicial/equipa', [TenantSetupWizardController::class, 'acknowledgeTeam'])->name('tenants.setup.team');
    Route::post('/organizacao/configuracao-inicial/concluir', [TenantSetupWizardController::class, 'complete'])->name('tenants.setup.complete');
    Route::post('/organizacao/configuracao-inicial/saltar', [TenantSetupWizardController::class, 'skip'])->name('tenants.setup.skip');
    Route::patch('/organizacao/configuracao-inicial/checklist', [TenantSetupWizardController::class, 'updateChecklist'])->name('tenants.setup.checklist');

    Route::post('/modulos/calendar/tarefas', [ModuleController::class, 'storeCalendarTask'])->name('modules.calendar.tasks.store');
    Route::patch('/modulos/calendar/tarefas/{task}/alternar', [ModuleController::class, 'toggleCalendarTask'])->name('modules.calendar.tasks.toggle');
    Route::delete('/modulos/calendar/tarefas/{task}', [ModuleController::class, 'destroyCalendarTask'])->name('modules.calendar.tasks.destroy');

    Route::get('/modulos/users/novo', [UserAdminController::class, 'create'])->name('modules.users.create');
    Route::post('/modulos/users', [UserAdminController::class, 'store'])->name('modules.users.store');
    Route::get('/modulos/users/{user}/editar', [UserAdminController::class, 'edit'])->name('modules.users.edit');
    Route::put('/modulos/users/{user}', [UserAdminController::class, 'update'])->name('modules.users.update');
    Route::delete('/modulos/users/{user}', [UserAdminController::class, 'destroy'])->name('modules.users.destroy');

    Route::get('/modulos/settings-countries/novo', [CountrySettingsController::class, 'create'])->name('modules.countries.create');
    Route::post('/modulos/settings-countries', [CountrySettingsController::class, 'store'])->name('modules.countries.store');
    Route::get('/modulos/settings-countries/{country}/editar', [CountrySettingsController::class, 'edit'])->name('modules.countries.edit');
    Route::put('/modulos/settings-countries/{country}', [CountrySettingsController::class, 'update'])->name('modules.countries.update');
    Route::delete('/modulos/settings-countries/{country}', [CountrySettingsController::class, 'destroy'])->name('modules.countries.destroy');

    Route::get('/modulos/{slug}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('/modulos/{slug}/novo', [ModuleController::class, 'create'])->name('modules.records.create');
    Route::post('/modulos/{slug}', [ModuleController::class, 'store'])->name('modules.records.store');
    Route::get('/modulos/{slug}/{record}/pdf', [ModuleController::class, 'recordPdf'])->name('modules.records.pdf');
    Route::get('/modulos/{slug}/{record}/editar', [ModuleController::class, 'edit'])->name('modules.records.edit');
    Route::put('/modulos/{slug}/{record}', [ModuleController::class, 'update'])->name('modules.records.update');
    Route::delete('/modulos/{slug}/{record}', [ModuleController::class, 'destroy'])->name('modules.records.destroy');

    Route::get('/entidades/criar', [EntityController::class, 'create'])->name('entities.create');
    Route::post('/entidades', [EntityController::class, 'store'])->name('entities.store');
    Route::get('/entidades/{entity}/editar', [EntityController::class, 'edit'])->name('entities.edit');
    Route::put('/entidades/{entity}', [EntityController::class, 'update'])->name('entities.update');
    Route::delete('/entidades/{entity}', [EntityController::class, 'destroy'])->name('entities.destroy');
    Route::post('/entidades/vies', [EntityController::class, 'viesLookup'])->name('entities.vies');
});

Route::middleware(['auth', 'active', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
