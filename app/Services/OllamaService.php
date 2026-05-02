<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OllamaService
{
    protected string $baseUrl;
    protected string $model;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url');
        $this->model = config('services.ollama.model');
    }

    /**
     * Send a chat request to Ollama.
     */
    public function chat(array $messages)
    {
        try {
            $response = Http::timeout(60)
                ->post("{$this->baseUrl}/api/chat", [
                    'model' => $this->model,
                    'messages' => $messages,
                    'stream' => false,
                ]);

            if ($response->failed()) {
                Log::error('Ollama API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama Service Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List local models.
     */
    public function listModels()
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/tags");
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (listModels): ' . $e->getMessage());
            return null;
        }
    }
}
