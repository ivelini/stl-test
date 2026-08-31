<?php

use App\Http\Controllers\Holds\HoldConfirmController;
use App\Http\Controllers\Holds\HoldController;
use App\Http\Controllers\Slots\SlotController;
use App\Http\Controllers\Slots\SlotHoldController;
use Illuminate\Support\Facades\Route;

Route::prefix('slots')
    ->name('slots.')
    ->group(function () {
        Route::get('/availability', [SlotController::class, 'index'])->name('availability');
        Route::post('/{slot}/hold', SlotHoldController::class)->name('hold');
    });

Route::prefix('holds')
    ->name('holds.')
    ->group(function () {
        Route::post('/{hold}/confirm', HoldConfirmController::class)->name('confirm');
        Route::delete('/{hold}', [HoldController::class, 'destroy'])->name('destroy');
    });
