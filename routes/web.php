<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\DecayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeasonController;
use App\Http\Controllers\SyncController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome_custom')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::controller(WorkspaceController::class)->group(function () {
        Route::get('/jogadores', 'players')->name('players.index');
        Route::get('/jogadores/{puuid}', 'player')->name('players.show');
        Route::put('/jogadores/{puuid}/anotacao', 'playerNote')->middleware('throttle:60,1')->name('players.note');
        Route::put('/partidas/{partida}/anotacao', 'matchNote')->whereNumber('partida')->middleware('throttle:60,1')->name('matches.note');
        Route::get('/caderno', 'index')->name('workspace.index');
        Route::post('/caderno/favoritos', 'favorite')->middleware('throttle:30,1')->name('workspace.favorites');
        Route::post('/caderno/metas', 'goal')->middleware('throttle:30,1')->name('workspace.goals');
        Route::delete('/caderno/{type}/{id}', 'destroy')->whereNumber('id')->name('workspace.destroy');
    });
    Route::get('/dashboard', [ContaController::class, 'index'])->name('dashboard');
    Route::get('/contas', fn () => to_route('dashboard', request()->only('days', 'season')))->name('contas.index');
    Route::post('/contas/sync-all', [SyncController::class, 'storeAll'])->middleware('throttle:sync')->name('contas.syncAll');
    Route::resource('contas', ContaController::class)->except('index')->where(['conta' => '[0-9]+']);
    Route::put('/contas/{conta}/decay', [DecayController::class, 'update'])->whereNumber('conta')->name('contas.decay.update');
    Route::delete('/contas/{conta}/decay', [DecayController::class, 'destroy'])->whereNumber('conta')->name('contas.decay.destroy');
    Route::post('/contas/{conta}/sync', [SyncController::class, 'store'])->whereNumber('conta')->middleware('throttle:sync')->name('contas.sync');
    Route::get('/contas/{conta}/sync', [SyncController::class, 'status'])->whereNumber('conta')->name('contas.syncStatus');
    Route::delete('/contas/{conta}/sync', [SyncController::class, 'destroy'])->whereNumber('conta')->name('contas.cancelSync');
    Route::get('/contas/{conta}/detalhes', fn (int $conta) => to_route('contas.show', $conta, 301))->whereNumber('conta')->name('contas.legacyShow');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', [AdminController::class, 'index'])->middleware('staff')->name('index');
        Route::middleware('admin')->group(function () {
            Route::get('/temporadas', [SeasonController::class, 'index'])->name('seasons.index');
            Route::post('/temporadas', [SeasonController::class, 'store'])->name('seasons.store');
            Route::post('/temporadas/{season}/iniciar', [SeasonController::class, 'activate'])->whereNumber('season')->name('seasons.activate');
            Route::patch('/usuarios/{id}/role', [AdminController::class, 'updateRole'])->whereNumber('id')->name('updateRole');
            Route::delete('/usuarios/{id}', [AdminController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });
    });
});

require __DIR__.'/auth.php';
