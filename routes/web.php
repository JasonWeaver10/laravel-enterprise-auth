<?php

use App\Http\Controllers\Auth\OktaController;
use App\Http\Controllers\Auth\SamlController;
use Illuminate\Support\Facades\Route;

Route::view('/dashboard', 'dashboard')->middleware('auth')->name('dashboard');

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [OktaController::class, 'login'])->name('login');

    Route::get('/auth/okta/redirect', [OktaController::class, 'redirect'])->name('okta.redirect');
    Route::get('/auth/okta/callback', [OktaController::class, 'callback'])->name('okta.callback');

    Route::get('/auth/saml/redirect', [SamlController::class, 'redirect'])->name('saml.redirect');
});

// Outside the guest group on purpose. An identity-provider-initiated sign-in
// arrives as a POST from Okta with no prior visit here, and it should still
// work for someone who already has a session open.
Route::post('/auth/saml/callback', [SamlController::class, 'callback'])->name('saml.callback');

// Public: the identity provider, or whoever is configuring it, reads this.
Route::get('/auth/saml/metadata', [SamlController::class, 'metadata'])->name('saml.metadata');

Route::post('/logout', [OktaController::class, 'logout'])->middleware('auth')->name('logout');
