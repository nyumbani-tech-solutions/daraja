<?php

use Illuminate\Support\Facades\Route;
use Nyumbani\Daraja\Http\Controllers\C2BController;
use Nyumbani\Daraja\Http\Controllers\StkCallbackController;
use Nyumbani\Daraja\Http\Middleware\EnsureRequestIsFromSafaricom;

Route::middleware(config('daraja.routes.middleware'))
    ->prefix(config('daraja.routes.prefix'))
    ->name('daraja.')
    ->group(function () {
        //  Only Safaricom's addresses reach the C2B routes
        Route::middleware(EnsureRequestIsFromSafaricom::class)->prefix('c2b')->name('c2b.')->group(function () {
            Route::post('validate', [C2BController::class, 'validation'])->name('validate');
            Route::post('confirm', [C2BController::class, 'confirmation'])->name('confirm');
        });

        //  An STK push result is trusted by the signature on its url
        Route::post('stk-callback', StkCallbackController::class)->middleware('signed')->name('stk-callback');

        //  A Pull notification
        Route::post('pull', [C2BController::class, 'pull'])->name('pull');
    });
