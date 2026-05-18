<?php

use App\Actions\AdminActions\Batch\TriggerDailySalesBatchAction;
use App\Actions\UserActions\Auth\LoginAction;
use App\Actions\UserActions\Auth\LogoutAction;
use App\Actions\UserActions\Auth\RegisterAction;
use App\Actions\UserActions\Cart\ShowCartAction;
use App\Actions\UserActions\Cart\UpdateCartAction;
use App\Actions\UserActions\Order\CreateOrderAction;
use App\Actions\UserActions\Order\GetOrderAction;
use App\Actions\UserActions\Order\ShowOrderAction;
use App\Actions\UserActions\Product\GetProductAction;
use App\Actions\UserActions\Product\ShowProductAction;
use App\Actions\UserActions\Wallet\ShowWalletAction;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', RegisterAction::class);
    Route::post('login', LoginAction::class);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', LogoutAction::class);

    Route::prefix('product')->group(function () {
        Route::get('/', GetProductAction::class);
        Route::get('/{id}', ShowProductAction::class);
    });

    Route::get('wallet', ShowWalletAction::class);

    Route::prefix('cart')->group(function () {
        Route::get('/', ShowCartAction::class);
        Route::post('/', UpdateCartAction::class);
    });

    Route::prefix('order')->middleware(['capacity.control', 'circuit.breaker:orders'])->group(function () {
        Route::get('/', GetOrderAction::class);
        Route::get('/{id}', ShowOrderAction::class);
        Route::post('/', CreateOrderAction::class);
    });
});

Route::prefix('admin')->middleware('auth:sanctum')->group(function () {
    Route::post('batch/daily-sales', TriggerDailySalesBatchAction::class);
});
