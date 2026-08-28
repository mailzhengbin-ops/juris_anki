<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AdminDeckController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('decks', [AdminDeckController::class, 'index'])->name('admin.decks');
    Route::get('users', [AdminUserController::class, 'index'])->name('admin.users');
});
