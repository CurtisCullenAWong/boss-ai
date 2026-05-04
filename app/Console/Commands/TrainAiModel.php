<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class TrainAiModel extends Command
{
    protected $signature = 'ai:train {--model=company-chatbot : The name of the resulting model}';
    protected $description = 'Compile training data and create a specialized Ollama model';

    public function handle(OllamaService $ollama)
    {
        $modelName = $this->option('model');
        $baseModel = config('services.ollama.base_model');

        $this->info("Starting AI model training for: {$modelName} (based on {$baseModel})...");

        if ($ollama->hasLocalModel($baseModel)) {
            $this->info("Base model '{$baseModel}' found locally.");
        } else {
            $this->warn("Base model '{$baseModel}' not found locally. Pulling it from Ollama now...");
        }

        $result = $ollama->train($modelName);

        if ($result) {
            $this->info("Successfully created model '{$modelName}'!");
            $this->info("You can now use this model by updating OLLAMA_MODEL={$modelName} in your .env");
            return 0;
        }

        $this->error("Could not create model on Ollama. Check logs for details.");
        return 1;
    }
}
