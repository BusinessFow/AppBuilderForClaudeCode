<?php

use App\Http\Controllers\Api\ClaudeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Claude API endpoints
Route::middleware('auth:sanctum')->prefix('projects/{project}/claude')->group(function () {
    Route::get('/session', [ClaudeController::class, 'getSession']);
    Route::post('/command', [ClaudeController::class, 'sendCommand']);
    Route::get('/output', [ClaudeController::class, 'getOutput']);
    Route::get('/stream', [ClaudeController::class, 'streamOutput']);
});