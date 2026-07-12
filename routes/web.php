<?php

use App\Http\Controllers\Client\MagicLinkController;
use App\Livewire\Agent\VectorStores;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Client\Access;
use App\Livewire\Client\Chat;
use App\Livewire\Client\CompanySelection;
use App\Livewire\Crm\Connect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Acesso do cliente final (magic link).
Route::get('/acesso', Access::class)->name('client.access');
Route::get('/acesso/{token}', [MagicLinkController::class, 'verify'])->name('client.magic-link.verify');

Route::middleware('auth:client')->group(function () {
    Route::get('/chat', Chat::class)->name('client.chat');
    Route::get('/selecionar-empresa', CompanySelection::class)->name('client.company-selection');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', Register::class)->name('register');
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');

    Route::get('/crm/connect', Connect::class)->name('crm.connect');

    Route::get('/agente/bases-de-conhecimento', VectorStores::class)->name('agent.vector-stores');

    Route::post('/logout', function () {
        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
