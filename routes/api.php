<?php

use App\Http\Controllers\Api\OllamaController;
use Illuminate\Support\Facades\Route;

Route::prefix('ollama')->group(function () {
    Route::get('/status', [OllamaController::class, 'status']);
    Route::get('/models', [OllamaController::class, 'models']);
    Route::post('/chat', [OllamaController::class, 'chat']);
    Route::post('/generate', [OllamaController::class, 'generate']);
    Route::post('/pull', [OllamaController::class, 'pull']);
    Route::post('/train', [OllamaController::class, 'train']);
});
