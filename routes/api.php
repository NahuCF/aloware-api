<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\LineController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoiceController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('users', UserController::class);
    Route::apiResource('lines', LineController::class);

    Route::get('/languages', [LanguageController::class, 'index']);
    Route::get('/skills', [SkillController::class, 'index']);

    Route::get('/voice/token', [VoiceController::class, 'token']);
});
