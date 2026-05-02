<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OllamaService;
use Illuminate\Http\Request;

class OllamaController extends Controller
{
    protected OllamaService $ollama;

    public function __construct(OllamaService $ollama)
    {
        $this->ollama = $ollama;
    }

    /**
     * Handle chat requests.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
            'messages' => 'nullable|array',
        ]);

        $messages = $request->input('messages', []);
        
        if (empty($messages)) {
            $messages[] = [
                'role' => 'user',
                'content' => $request->input('prompt'),
            ];
        }

        $result = $this->ollama->chat($messages);

        if (!$result) {
            return response()->json(['error' => 'Failed to communicate with Ollama'], 500);
        }

        return response()->json($result);
    }

    /**
     * List available models.
     */
    public function models()
    {
        $result = $this->ollama->listModels();

        if (!$result) {
            return response()->json(['error' => 'Failed to fetch models from Ollama'], 500);
        }

        return response()->json($result);
    }
}
