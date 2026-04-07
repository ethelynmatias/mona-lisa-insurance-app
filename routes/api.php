<?php

use App\Http\Controllers\Api\CognitoFormsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cognito Forms API Routes
|--------------------------------------------------------------------------
|
| All routes are protected by the auth:sanctum middleware.
| Base URL: /api/cognito
|
*/

Route::middleware('auth:sanctum')->prefix('cognito')->group(function () {

    // Forms
    Route::get('forms',                         [CognitoFormsController::class, 'index']);
    Route::get('forms/{formId}',                [CognitoFormsController::class, 'show']);
    Route::get('forms/{formId}/fields',         [CognitoFormsController::class, 'fields']);

    // Entries
    Route::get('forms/{formId}/entries',                    [CognitoFormsController::class, 'entries']);
    Route::post('forms/{formId}/entries',                   [CognitoFormsController::class, 'createEntry']);
    Route::get('forms/{formId}/entries/{entryId}',          [CognitoFormsController::class, 'entry']);
    Route::put('forms/{formId}/entries/{entryId}',          [CognitoFormsController::class, 'updateEntry']);
    Route::delete('forms/{formId}/entries/{entryId}',       [CognitoFormsController::class, 'deleteEntry']);

});
