<?php

namespace App\Console\Commands;

use App\Services\OllamaService;
use Illuminate\Console\Command;

class ListAiModels extends Command
{
    protected $signature = 'ai:list';
    protected $description = 'List all available local Ollama models';

    public function handle(OllamaService $ollama)
    {
        $this->info("Fetching available models from Ollama...");

        $result = $ollama->listModels();

        if (!$result || !isset($result['models'])) {
            $this->error("Could not fetch models. Is Ollama running?");
            return 1;
        }

        if (empty($result['models'])) {
            $this->warn("No models found locally.");
            return 0;
        }

        $headers = ['Name', 'Size (GB)', 'Format', 'Family', 'Modified'];
        $rows = [];

        foreach ($result['models'] as $model) {
            $sizeGb = round($model['size'] / (1024 * 1024 * 1024), 2);
            $rows[] = [
                $model['name'],
                $sizeGb,
                $model['details']['format'] ?? 'N/A',
                $model['details']['family'] ?? 'N/A',
                \Illuminate\Support\Carbon::parse($model['modified_at'])->diffForHumans(),
            ];
        }

        $this->table($headers, $rows);

        $configuredModel = config('services.ollama.model');
        $this->info("\nCurrently configured model in .env: " . ($configuredModel ?: 'None'));

        return 0;
    }
}
