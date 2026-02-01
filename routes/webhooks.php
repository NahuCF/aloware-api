<?php

use App\Http\Controllers\Webhook\TwilioController;
use Illuminate\Support\Facades\Route;

Route::prefix('twilio/voice')->group(function () {
    Route::post('/incoming', [TwilioController::class, 'incoming']);
    Route::post('/handle-input', [TwilioController::class, 'handleInput']);
});
