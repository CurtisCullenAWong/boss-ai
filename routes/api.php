<?php

use App\Http\Controllers\Api\OllamaController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\JobApplicantController;
use Illuminate\Support\Facades\Route;

Route::prefix('ollama')->group(function () {
    Route::get('/status', [OllamaController::class, 'status']);
    Route::get('/models', [OllamaController::class, 'models']);
    Route::post('/chat', [OllamaController::class, 'chat']);
    Route::post('/generate', [OllamaController::class, 'generate']);
    Route::post('/pull', [OllamaController::class, 'pull']);
    Route::post('/train', [OllamaController::class, 'train']);
});

Route::apiResource('jobs', JobController::class);

Route::apiResource('applicants', JobApplicantController::class);
Route::get('/jobs/{job}/applicants', [JobApplicantController::class, 'byJob']);
Route::patch('/applicants/{applicant}/status', [JobApplicantController::class, 'updateStatus']);
