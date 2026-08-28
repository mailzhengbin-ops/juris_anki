<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDeckController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');

    Route::get('decks', [AdminDeckController::class, 'index'])->name('admin.decks');
    Route::post('decks/import', [AdminDeckController::class, 'import'])->name('admin.decks.import');
    Route::get('decks/{deck}', [AdminDeckController::class, 'show'])->name('admin.decks.show');
    Route::patch('decks/{deck}', [AdminDeckController::class, 'update'])->name('admin.decks.update');
    Route::delete('decks/{deck}', [AdminDeckController::class, 'destroy'])->name('admin.decks.destroy');
    Route::patch('sections/{section}', [AdminDeckController::class, 'updateSection'])->name('admin.sections.update');
    Route::delete('sections/{section}', [AdminDeckController::class, 'destroySection'])->name('admin.sections.destroy');
    Route::patch('cards/{card}', [AdminDeckController::class, 'updateCard'])->name('admin.cards.update');
    Route::delete('cards/{card}', [AdminDeckController::class, 'destroyCard'])->name('admin.cards.destroy');

    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users');
    Route::get('users/{user}', [AdminUserController::class, 'show'])->name('admin.users.show');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('admin.users.destroy');
});
