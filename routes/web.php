<?php

use App\Http\Controllers\SelectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('recite');
    }

    return inertia('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('recite', 'recite/index')->name('recite');

    Route::get('select', [SelectController::class, 'index'])->name('select');
    Route::post('select/deck', [SelectController::class, 'setSelectedDeck'])->name('select.deck');

    Route::inertia('stats', 'stats/index')->name('stats');
});

require __DIR__.'/settings.php';
