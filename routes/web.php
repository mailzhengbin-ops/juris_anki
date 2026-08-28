<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('recite');
    }

    return inertia('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('recite', 'recite/index')->name('recite');
    Route::inertia('select', 'select/index')->name('select');
    Route::inertia('stats', 'stats/index')->name('stats');
});

require __DIR__.'/settings.php';
