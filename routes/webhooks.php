<?php

use App\Http\Controllers\Webhook\TaskRouterController;
use App\Http\Controllers\Webhook\TwilioController;
use Illuminate\Support\Facades\Route;

Route::middleware('twilio.signature')->prefix('twilio')->group(function () {
    Route::prefix('voice')->name('twilio.voice.')->group(function () {
        Route::post('/incoming', [TwilioController::class, 'incoming']);
        Route::post('/outbound', [TwilioController::class, 'outbound']);

        Route::post('/handle-input', [TwilioController::class, 'handleInput'])->name('handle-input');
        Route::post('/dial-complete', [TwilioController::class, 'dialComplete'])->name('dial-complete');
        Route::post('/queue-status', [TwilioController::class, 'queueStatus'])->name('queue-status');
    });

    Route::prefix('taskrouter')->group(function () {
        Route::post('/assignment', [TaskRouterController::class, 'assignment']);
        Route::post('/events', [TaskRouterController::class, 'events']);
    });
});
