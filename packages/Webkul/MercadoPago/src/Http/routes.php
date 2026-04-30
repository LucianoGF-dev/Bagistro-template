<?php

use Illuminate\Support\Facades\Route;
use Webkul\MercadoPago\Http\Controllers\MercadoPagoController;

Route::prefix('mercadopago')
    ->name('mercadopago.')
    ->middleware(['web']) // Importante para sesión y CSRF
    ->group(function () {
        Route::post('/redirect', [MercadoPagoController::class, 'createPreference'])->name('redirect');
        Route::any('/webhook', [MercadoPagoController::class, 'handleWebhook'])->name('webhook');
        Route::get('/success', [MercadoPagoController::class, 'handleSuccess'])->name('success');
        Route::get('/failure', [MercadoPagoController::class, 'handleFailure'])->name('failure');
    });