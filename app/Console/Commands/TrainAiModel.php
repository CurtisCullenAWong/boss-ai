<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class TrainAiModel extends Command
{
    protected $signature = 'ai:train {--model=company-assistant : The name of the resulting model}';
    protected $description = 'Compile training data and create a specialized Ollama model';

    public function handle(OllamaService $ollama)
    {
        $modelName = $this->option('model');
        $this->info("Starting AI model training for: {$modelName} (based on gemma3:4b)...");

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
