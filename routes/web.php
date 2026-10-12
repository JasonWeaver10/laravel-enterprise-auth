<?php

use App\Http\Controllers\Auth\OktaController;
use Illuminate\Support\Facades\Route;

Route::view('/dashboard', 'dashboard')->middleware('auth')->name('dashboard');

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [OktaController::class, 'login'])->name('login');
    Route::get('/auth/okta/redirect', [OktaController::class, 'redirect'])->name('okta.redirect');
    Route::get('/auth/okta/callback', [OktaController::class, 'callback'])->name('okta.callback');
});

Route::post('/logout', [OktaController::class, 'logout'])->middleware('auth')->name('logout');
