<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class PushAiModel extends Command
{
    protected $signature = 'ai-cloud:push {model? : The name of the local model to push} {--username= : The Ollama username/namespace}';
    protected $description = 'Push the current Modelfile/model to the Ollama cloud library';

    public function handle(OllamaService $ollama)
    {
        $modelName = $this->argument('model') ?: config('services.ollama.model');
        $username = $this->option('username') ?: config('services.ollama.username');

        if (!$modelName) {
            $this->error("No model specified and OLLAMA_MODEL is not set in .env");
            return 1;
        }

        if (!$username) {
            $this->error("No username specified and OLLAMA_USERNAME is not set in .env");
            return 1;
        }

        $destinationModel = "{$username}/{$modelName}";

        if (!$ollama->hasLocalModel($destinationModel) && !$ollama->hasLocalModel($modelName)) {
            $this->error("Local model '{$destinationModel}' (or '{$modelName}') not found. Please run 'sail artisan ai-cloud:train' first.");
            return 1;
        }

        $modelfilePath = base_path('training/build/CloudModelfile');
        if (!file_exists($modelfilePath)) {
            $modelfilePath = base_path('training/build/Modelfile');
        }
        if (!file_exists($modelfilePath)) {
            $modelfilePath = base_path('Modelfile');
        }

        if (!file_exists($modelfilePath)) {
            $this->error("Modelfile not found at training/build/CloudModelfile, training/build/Modelfile, or root Modelfile. Please create or generate a Modelfile first.");
            return 1;
        }

        $this->info("Preparing to push model '{$modelName}' to '{$destinationModel}'...");
        $this->info("Training/creating cloud model '{$destinationModel}' directly from Modelfile ({$modelfilePath})...");

        $modelfileContent = file_get_contents($modelfilePath);
        $baseModel = config('services.ollama.cloud_base_model') ?: config('services.ollama.base_model');

        if (!$ollama->createModel(
            $destinationModel,
            $modelfileContent,
            $baseModel,
            $ollama->getFullSystemPrompt(),
            $ollama->getCloudOptions()
        )) {
            $this->error("Failed to train/create model '{$destinationModel}' from Modelfile. Check Ollama logs.");
            return 1;
        }

        $this->info("Successfully trained model '{$destinationModel}'.");
        $this->info("Pushing model '{$destinationModel}' to Ollama cloud...");
        $this->info("This may take several minutes depending on the model size and your internet connection...");

        $result = $ollama->pushModel($destinationModel);

        if ($result !== null) {
            $this->info("Successfully pushed model '{$destinationModel}' to Ollama cloud!");
            return 0;
        }

        $this->error("Failed to push model '{$destinationModel}'. Check Ollama logs.");
        return 1;
    }
}
