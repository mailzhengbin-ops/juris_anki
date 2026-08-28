<?php

use App\Http\Controllers\DeckController;
use App\Http\Controllers\ScopeController;
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
    Route::post('select/source', [SelectController::class, 'setActiveSource'])->name('select.source');

    Route::post('scope/toggle', [ScopeController::class, 'toggle'])->name('scope.toggle');

    Route::post('decks/import', [DeckController::class, 'import'])->name('decks.import');
    Route::delete('decks/{deck}', [DeckController::class, 'destroy'])->name('decks.destroy');

    Route::inertia('stats', 'stats/index')->name('stats');
});

require __DIR__.'/settings.php';
