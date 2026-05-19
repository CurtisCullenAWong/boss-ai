<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

use function Laravel\Prompts\text;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

class RunAiModel extends Command
{
    protected $signature = 'ai:run {model? : The name of the model to run}';
    protected $description = 'Run an interactive chat session with an AI model';

    public function handle()
    {
        $modelName = $this->argument('model') ?: config('services.ollama.model');

        if (!$modelName) {
            error("No model specified and default model is not set in config.");
            return 1;
        }

        // Temporarily override the config so OllamaService uses the requested model
        Config::set('services.ollama.model', $modelName);

        // Resolve a fresh instance of the service with the new config
        $ollama = app(OllamaService::class);

        note("Starting interactive session with model '{$modelName}'. Type 'exit' or 'quit' to end.");

        $messages = [];

        while (true) {
            $input = text(
                label: 'You',
                placeholder: 'Type your message here...',
                required: false
            );

            if ($input === null || empty(trim($input))) {
                continue;
            }

            if (in_array(strtolower(trim($input)), ['exit', 'quit'])) {
                note('Ending session. Goodbye! (One Team)');
                break;
            }

            $messages[] = [
                'role' => 'user',
                'content' => $input,
            ];

            $response = spin(
                fn() => $ollama->chat($messages),
                'AI is thinking...'
            );

            if ($response && isset($response['message']['content'])) {
                $reply = $response['message']['content'];

                // Format basic markdown bold syntax for Symfony console
                $formattedReply = preg_replace('/\*\*(.*?)\*\*/', '<options=bold>$1</>', $reply);

                $this->line("");
                $this->line("  <fg=cyan;options=bold>Bosco (AI):</> {$formattedReply}");
                $this->line("");

                $messages[] = [
                    'role' => 'assistant',
                    'content' => $reply,
                ];
            } else {
                error("Failed to get a response from the model.");
                // Remove the last user message so they can try again
                array_pop($messages);
            }
        }

        return 0;
    }
}
