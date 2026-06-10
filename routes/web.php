<?php

use App\Http\Middleware\ForcaTrocaSenha;
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
});

Route::redirect('/', '/login');
