<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class TrainAiCloudModel extends Command
{
    protected $signature = 'ai-cloud:train {model? : The name of the local model} {--username= : The Ollama username/namespace}';
    protected $description = 'Compile training data and create a specialized cloud-formatted Ollama model';

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
        $baseModel = config('services.ollama.cloud_base_model') ?: config('services.ollama.base_model');

        $this->info("Starting AI cloud model training for: {$destinationModel} (based on {$baseModel})...");

        if ($ollama->hasLocalModel($baseModel)) {
            $this->info("Base model '{$baseModel}' found locally.");
        } else {
            $this->warn("Base model '{$baseModel}' not found locally. Pulling it from Ollama now...");
        }

        $result = $ollama->trainCloud($destinationModel, $baseModel);

        if ($result) {
            $this->info("Successfully created cloud model '{$destinationModel}'!");
            $this->info("Cloud Modelfile generated at: training/build/CloudModelfile");
            $this->info("You can now push this model using: sail artisan ai-cloud:push");
            return 0;
        }

        $this->error("Could not create cloud model on Ollama. Check logs for details.");
        return 1;
    }
}
