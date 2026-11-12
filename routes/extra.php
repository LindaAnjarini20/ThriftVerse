<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// Rute tambahan (dimuat oleh AppServiceProvider, di dalam grup middleware "web").
Route::middleware('guest')->group(function () {
    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
