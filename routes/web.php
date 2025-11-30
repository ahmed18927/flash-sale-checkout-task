<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

use App\Http\Controllers\ProductController;
use App\Http\Controllers\HoldController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;



Route::prefix('api')
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->group(function () {
        Route::post('/holds', [HoldController::class, 'store']);
        Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    });
