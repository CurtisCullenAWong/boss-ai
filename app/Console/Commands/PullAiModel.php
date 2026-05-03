<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class PullAiModel extends Command
{
    protected $signature = 'ai:pull {model? : The name of the model to pull (defaults to OLLAMA_BASE_MODEL)}';
    protected $description = 'Pull a model from the Ollama library';

    public function handle(OllamaService $ollama)
    {
        $modelName = $this->argument('model') ?: config('services.ollama.base_model');

        if (!$modelName) {
            $this->error("No model specified and OLLAMA_BASE_MODEL is not set in .env");
            return 1;
        }

        $this->info("Pulling model '{$modelName}' from Ollama library...");
        $this->info("This may take several minutes depending on your internet connection...");

        // We use streaming=true in the service if we wanted progress, 
        // but current implementation is simple. 
        // Let's check if we can improve it to show progress if possible.
        
        $result = $ollama->pullModel($modelName);

        if ($result) {
            $this->info("Successfully pulled model '{$modelName}'!");
            return 0;
        }

        $this->error("Failed to pull model '{$modelName}'. Check Ollama logs.");
        return 1;
    }
}
