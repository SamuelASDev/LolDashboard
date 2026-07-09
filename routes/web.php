<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ContaController;
use App\Http\Controllers\AdminController;

// Página inicial redireciona para o login se não estiver logado
Route::get('/', function () {
    return view('welcome_custom');
});

// 🔒 Todas as rotas abaixo exigem Login ativo
Route::middleware(['auth', 'verified'])->group(function () {
    
    Route::get('/dashboard', [ContaController::class, 'index'])->name('dashboard');
    Route::resource('contas', ContaController::class)->except(['index']);
    Route::post('/contas/{id}/sync', [ContaController::class, 'sync'])->name('contas.sync');
    Route::post('/contas/sync-all', [ContaController::class, 'syncAll'])->name('contas.syncAll');

    // 👑 ÁREA DO ADMINISTRADOR (Protegida por nível de acesso)
    Route::middleware(['admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/usuarios', [AdminController::class, 'index'])->name('index');
        Route::patch('/usuarios/{id}/role', [AdminController::class, 'updateRole'])->name('updateRole');
        Route::delete('/usuarios/{id}', [AdminController::class, 'destroy'])->name('destroy');
    });
});
// Carrega as rotas nativas do Breeze (Login, Registro, Logout, etc.)
require __DIR__.'/auth.php';