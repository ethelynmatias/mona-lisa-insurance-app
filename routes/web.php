<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CognitoController;
use Illuminate\Support\Facades\Route;

// Guest only
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

// Authenticated only
Route::middleware('auth')->group(function () {
    Route::get('/dashboard',              [CognitoController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/forms/{formId}',          [CognitoController::class, 'show'])->name('forms.show');
    Route::post('/dashboard/forms/{formId}/mappings', [CognitoController::class, 'saveMappings'])->name('forms.mappings.save');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
