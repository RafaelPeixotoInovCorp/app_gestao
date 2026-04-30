<?php

use App\Http\Controllers\EntityController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProfileController;
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

Route::get('/dashboard', [ModuleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('/modulos/calendar/tarefas', [ModuleController::class, 'storeCalendarTask'])->name('modules.calendar.tasks.store');
    Route::patch('/modulos/calendar/tarefas/{task}/alternar', [ModuleController::class, 'toggleCalendarTask'])->name('modules.calendar.tasks.toggle');
    Route::delete('/modulos/calendar/tarefas/{task}', [ModuleController::class, 'destroyCalendarTask'])->name('modules.calendar.tasks.destroy');

    Route::get('/modulos/{slug}', [ModuleController::class, 'show'])->name('modules.show');
    Route::get('/modulos/{slug}/novo', [ModuleController::class, 'create'])->name('modules.records.create');
    Route::post('/modulos/{slug}', [ModuleController::class, 'store'])->name('modules.records.store');
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
