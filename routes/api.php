<?php

use App\Http\Controllers\Slots\SlotController;
use Illuminate\Support\Facades\Route;

Route::prefix('slots')
    ->name('slots.')
    ->group(function () {
        Route::get('/availability', [SlotController::class, 'index'])->name('availability');
    });
