<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ForcaTrocaSenha;
use App\Livewire\Admin\Alcadas\ListaAlcadas;
use App\Livewire\Admin\CentrosCusto\ListaCentrosCusto;
use App\Livewire\Admin\Fornecedores\ListaFornecedores;
use App\Livewire\Admin\Unidades\ListaUnidades;
use App\Livewire\Admin\Usuarios\ListaUsuarios;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\TrocarSenha;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::middleware(['auth', ForcaTrocaSenha::class])->group(function () {
    Route::get('/senha/trocar', TrocarSenha::class)->name('senha.trocar');

    Route::post('/logout', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

    // Fase 1 — somente Admin
    Route::middleware(AdminMiddleware::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/unidades', ListaUnidades::class)->name('unidades');
        Route::get('/usuarios', ListaUsuarios::class)->name('usuarios');
        Route::get('/fornecedores', ListaFornecedores::class)->name('fornecedores');
        Route::get('/alcadas', ListaAlcadas::class)->name('alcadas');
        Route::get('/centros-custo', ListaCentrosCusto::class)->name('centros-custo');
    });
});

Route::redirect('/', '/login');
