<?php

use App\Http\Controllers\Api\OllamaController;
use Illuminate\Support\Facades\Route;

Route::prefix('ollama')->group(function () {
    Route::post('/chat', [OllamaController::class, 'chat']);
    Route::get('/models', [OllamaController::class, 'models']);
});
