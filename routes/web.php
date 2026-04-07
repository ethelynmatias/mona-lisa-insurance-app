<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public
Route::get('/', function () {
    return Inertia::render('Home');
});

// Guest only
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Authenticated only
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
