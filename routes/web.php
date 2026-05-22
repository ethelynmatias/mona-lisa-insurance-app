<?php

use App\Http\Controllers\LogsController;
use App\Http\Controllers\NotificationsController;
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
    Route::get('/dashboard/forms/{formId}',                   [CognitoController::class, 'show'])->name('forms.show');
    Route::get('/dashboard/forms/{formId}/mappings',          [CognitoController::class, 'viewMappings'])->name('forms.mappings.view');
    Route::post('/dashboard/forms/{formId}/mappings',         [CognitoController::class, 'saveMappings'])->name('forms.mappings.save');

    // Settings
    Route::get('/settings',                      [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings/profile',              [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/password',             [SettingsController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/users',               [SettingsController::class, 'createUser'])->name('settings.users.create')->middleware('role:admin');
    Route::patch('/settings/users/{user}/status', [SettingsController::class, 'toggleUserStatus'])->name('settings.users.status')->middleware('role:admin');
    Route::delete('/settings/users/{user}',       [SettingsController::class, 'deleteUser'])->name('settings.users.delete')->middleware('role:admin');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Notifications
    Route::get('/notifications',                        [NotificationsController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/{log}/read',           [NotificationsController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all',             [NotificationsController::class, 'markAllRead'])->name('notifications.read-all');
    Route::get('/notifications/unread-count',           [NotificationsController::class, 'unreadCount'])->name('notifications.unread-count');

    // Logs
    Route::get('/logs',          [LogsController::class, 'index'])->name('logs.index')->middleware('role:admin');
    Route::get('/logs/{log}',    [LogsController::class, 'show'])->name('logs.show')->middleware('role:admin');
    Route::delete('/logs/clear', [LogsController::class, 'clear'])->name('logs.clear')->middleware('role:admin');

    // Webhook history management
    Route::delete('/webhook/history',                   [CognitoWebhookController::class, 'clearAll'])->name('webhook.history.clear');
    Route::post('/webhook/history/{log}/rerun',         [CognitoWebhookController::class, 'rerunSync'])->name('webhook.history.rerun');
    Route::delete('/webhook/entry/{log}',               [CognitoWebhookController::class, 'deleteEntry'])->name('webhook.history.delete');
    Route::delete('/webhook/history/{formId}',          [CognitoWebhookController::class, 'clearByForm'])->name('webhook.history.clear-form');
});
