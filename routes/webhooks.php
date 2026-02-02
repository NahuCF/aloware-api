<?php

use App\Http\Controllers\Webhook\TaskRouterController;
use App\Http\Controllers\Webhook\TwilioController;
use Illuminate\Support\Facades\Route;

Route::prefix('twilio/voice')->middleware('twilio.signature')->group(function () {
    Route::post('/incoming', [TwilioController::class, 'incoming']);
    Route::post('/handle-input', [TwilioController::class, 'handleInput']);
    Route::post('/dial-complete', [TwilioController::class, 'dialComplete']);
    Route::post('/outbound', [TwilioController::class, 'outbound']);
});

Route::prefix('twilio/taskrouter')->middleware('twilio.signature')->group(function () {
    Route::post('/assignment', [TaskRouterController::class, 'assignment']);
    Route::post('/events', [TaskRouterController::class, 'events']);
});
