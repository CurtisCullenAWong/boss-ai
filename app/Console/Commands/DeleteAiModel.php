<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class DeleteAiModel extends Command
{
    protected $signature = 'ai:delete {model : The name of the model to delete} {--force : Skip confirmation}';
    protected $description = 'Delete a local Ollama model';

    public function handle(OllamaService $ollama)
    {
        $modelName = $this->argument('model');

        if (!$ollama->hasLocalModel($modelName)) {
            $this->error("Model '{$modelName}' not found locally.");
            return 1;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete model '{$modelName}'? This cannot be undone.")) {
                $this->info("Deletion cancelled.");
                return 0;
            }
        }

        $this->info("Deleting model '{$modelName}'...");

        if ($ollama->deleteModel($modelName)) {
            $this->info("Successfully deleted model '{$modelName}'.");
            return 0;
        }

        $this->error("Failed to delete model '{$modelName}'. Check Ollama logs.");
        return 1;
    }
}
