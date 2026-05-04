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

        $scopePrompt = $this->extractLatestUserPrompt($messages, (string) $request->input('prompt'));

        if (!$this->ollama->isInScopePrompt($scopePrompt)) {
            return response()->json([
                'model' => config('services.ollama.model'),
                'created_at' => now()->toISOString(),
                'message' => [
                    'role' => 'assistant',
                    'content' => $this->ollama->outOfScopeRefusal($scopePrompt),
                ],
                'done' => true,
                'done_reason' => 'stop',
                'guardrail' => 'out_of_scope_blocked',
            ]);
        }

        $result = $this->ollama->chat($messages);

        if (!$result) {
            return response()->json(['error' => 'Failed to communicate with Ollama'], 500);
        }

        return response()->json($result);
    }

    /**
     * Handle single generate requests.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string',
        ]);

        $prompt = (string) $request->input('prompt');

        if (!$this->ollama->isInScopePrompt($prompt)) {
            return response()->json([
                'model' => config('services.ollama.model'),
                'created_at' => now()->toISOString(),
                'response' => $this->ollama->outOfScopeRefusal($prompt),
                'done' => true,
                'done_reason' => 'stop',
                'guardrail' => 'out_of_scope_blocked',
            ]);
        }

        $result = $this->ollama->generate($prompt);

        if (!$result) {
            return response()->json(['error' => 'Failed to communicate with Ollama'], 500);
        }

        return response()->json($result);
    }

    /**
     * Trigger model training (creation with knowledge base).
     */
    public function train(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'base_model' => 'nullable|string',
        ]);

        $result = $this->ollama->train(
            $request->input('name'),
            $request->input('base_model')
        );

        if (!$result) {
            return response()->json(['error' => 'Failed to trigger training'], 500);
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

    /**
     * Pull a new model.
     */
    public function pull(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $result = $this->ollama->pullModel($request->input('name'));

        if (!$result) {
            return response()->json(['error' => 'Failed to pull model'], 500);
        }

        return response()->json($result);
    }

    /**
     * Get service status.
     */
    public function status()
    {
        $models = $this->ollama->listModels();
        
        return response()->json([
            'status' => $models ? 'online' : 'offline',
            'configured_model' => config('services.ollama.model'),
            'base_url' => config('services.ollama.url'),
        ]);
    }

    /**
     * Extract the newest user message content from a message list.
     */
    protected function extractLatestUserPrompt(array $messages, string $fallbackPrompt): string
    {
        for ($index = count($messages) - 1; $index >= 0; $index--) {
            $message = $messages[$index] ?? null;

            if (!is_array($message)) {
                continue;
            }

            if (($message['role'] ?? null) !== 'user') {
                continue;
            }

            $content = $message['content'] ?? '';

            if (is_string($content) && trim($content) !== '') {
                return $content;
            }
        }

        return $fallbackPrompt;
    }
}
