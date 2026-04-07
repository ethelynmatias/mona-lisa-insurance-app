<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CognitoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Webhook\CognitoWebhookController;
use Illuminate\Support\Facades\Route;

// Public webhooks (no auth, no CSRF)
Route::post('/webhook/cognito', [CognitoWebhookController::class, 'receive'])->name('webhook.cognito');

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

    // Settings
    Route::get('/settings',                      [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/profile',              [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password',             [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/users',               [SettingsController::class, 'createUser'])->name('settings.users.create')->middleware('role:admin');
    Route::patch('/settings/users/{user}/status', [SettingsController::class, 'toggleUserStatus'])->name('settings.users.status')->middleware('role:admin');
    Route::delete('/settings/users/{user}',       [SettingsController::class, 'deleteUser'])->name('settings.users.delete')->middleware('role:admin');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
