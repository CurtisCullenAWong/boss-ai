<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class OllamaService
{
    protected string $baseUrl;
    protected string $model;
    protected string $systemPrompt;
    protected int $cacheTtl;
    protected array $options;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url');
        $this->model = config('services.ollama.model');
        $this->systemPrompt = $this->loadSystemPrompt();
        $this->cacheTtl = (int) config('services.ollama.cache_ttl', 3600);
        $this->options = config('services.ollama.options', []);
        
        // Ensure numeric options are correctly typed for Ollama's strict API
        if (isset($this->options['num_ctx'])) {
            $this->options['num_ctx'] = (int) $this->options['num_ctx'];
        }
        if (isset($this->options['temperature'])) {
            $this->options['temperature'] = (float) $this->options['temperature'];
        }
    }

    /**
     * Send a chat request to Ollama.
     */
    public function chat(array $messages, array $customOptions = [])
    {
        try {
            if ($this->systemPrompt && (!isset($messages[0]['role']) || $messages[0]['role'] !== 'system')) {
                array_unshift($messages, [
                    'role' => 'system',
                    'content' => $this->systemPrompt,
                ]);
            }

            // Create a unique cache key for this message set and model
            $cacheKey = 'ollama_chat_' . md5(json_encode([
                'model' => $this->model,
                'messages' => $messages,
                'options' => array_merge($this->options, $customOptions)
            ]));

            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($messages, $customOptions) {
                $response = Http::timeout(120)
                    ->post("{$this->baseUrl}/api/chat", [
                        'model' => $this->model,
                        'messages' => $messages,
                        'stream' => false,
                        'options' => array_merge($this->options, $customOptions),
                    ]);

                if ($response->failed()) {
                    Log::error('Ollama API request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $response->json();
            });
        } catch (\Exception $e) {
            Log::error('Ollama Service Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send a generate request to Ollama (single completion).
     */
    public function generate(string $prompt, array $customOptions = [])
    {
        try {
            $cacheKey = 'ollama_gen_' . md5(json_encode([
                'model' => $this->model,
                'prompt' => $prompt,
                'options' => array_merge($this->options, $customOptions)
            ]));

            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($prompt, $customOptions) {
                $response = Http::timeout(120)
                    ->post("{$this->baseUrl}/api/generate", [
                        'model' => $this->model,
                        'prompt' => $prompt,
                        'system' => $this->systemPrompt,
                        'stream' => false,
                        'options' => array_merge($this->options, $customOptions),
                    ]);

                if ($response->failed()) {
                    Log::error('Ollama API generate request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $response->json();
            });
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (generate): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Compile knowledge base from training data files.
     */
    public function compileKnowledgeBase(): string
    {
        $knowledgeBase = "";
        $allowedSourcesPath = base_path('training/allowed_sources.json');

        if (File::exists($allowedSourcesPath)) {
            $allowedSources = json_decode(File::get($allowedSourcesPath), true);
            if (isset($allowedSources['allowed_paths']) && is_array($allowedSources['allowed_paths'])) {
                foreach ($allowedSources['allowed_paths'] as $path) {
                    $fullPath = base_path($path);
                    if (File::exists($fullPath)) {
                        $knowledgeBase .= File::get($fullPath) . "\n\n";
                    } else {
                        Log::warning("Knowledge base source file not found: {$fullPath}");
                    }
                }
            }
        } else {
            $basePath = base_path('training/data');
            if (File::exists($basePath)) {
                $compartments = File::directories($basePath);

                foreach ($compartments as $directory) {
                    $name = basename($directory);
                    $files = File::allFiles($directory);
                    $knowledgeBase .= "\n### " . strtoupper($name) . " DATA ###\n";
                    foreach ($files as $file) {
                        if (in_array($file->getExtension(), ['txt', 'md', 'json'])) {
                            $knowledgeBase .= File::get($file->getPathname()) . "\n";
                        }
                    }
                }
            }
        }

        // Save reference files for debugging and review
        File::put(base_path('training/full_knowledge_base.txt'), trim($knowledgeBase));

        return trim($knowledgeBase);
    }

    /**
     * Train a new model based on the current configuration and training data.
     */
    public function train(string $modelName, ?string $baseModel = null): ?array
    {
        $baseModel = $baseModel ?? config('services.ollama.base_model', 'gemma3:4b');
        
        // Ensure base model is available locally
        Log::info("Checking/Pulling base model: {$baseModel}");
        $this->pullModel($baseModel);

        $knowledgeBase = $this->compileKnowledgeBase();
        
        // Construct Modelfile with parameters from config
        $modelfileContent = "FROM {$baseModel}\n";
        foreach ($this->options as $key => $value) {
            $modelfileContent .= "PARAMETER {$key} {$value}\n";
        }
        
        $modelfileContent .= "SYSTEM \"\"\"\n" .
                           trim($this->systemPrompt) . "\n\n" .
                           "KNOWLEDGE BASE:\n" .
                           $knowledgeBase . "\n\n" .
                           "Always use the provided knowledge base to answer questions.\n" .
                           "\"\"\"";

        // Save reference Modelfile for debugging and review
        File::put(base_path('training/Modelfile'), $modelfileContent);

        return $this->createModel($modelName, $modelfileContent, $baseModel);
    }

    /**
     * Create a new model from a Modelfile.
     */
    public function createModel(string $name, string $modelfile, ?string $from = null, bool $stream = false)
    {
        try {
            $response = Http::timeout(300)->post("{$this->baseUrl}/api/create", [
                'name' => $name,
                'modelfile' => $modelfile,
                'from' => $from,
                'stream' => $stream,
            ]);

            if ($response->failed()) {
                Log::error('Ollama API createModel failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'name' => $name,
                ]);
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (createModel): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Pull a model from the Ollama library.
     */
    public function pullModel(string $name, bool $stream = false)
    {
        try {
            $response = Http::timeout(600)->post("{$this->baseUrl}/api/pull", [
                'name' => $name,
                'stream' => $stream,
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (pullModel): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List local models.
     */
    public function listModels()
    {
        try {
            $response = Http::get("{$this->baseUrl}/api/tags");
            return $response->json();
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (listModels): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Load the system prompt from the modular prompts directory.
     */
    protected function loadSystemPrompt(): string
    {
        $promptsPath = base_path('training/prompts');
        $configPath = base_path('training/configs/model_config.json');
        
        if (File::isDirectory($promptsPath)) {
            $promptOrder = [];
            if (File::exists($configPath)) {
                $config = json_decode(File::get($configPath), true);
                $promptOrder = $config['training_options']['prompt_order'] ?? [];
            }

            if (!empty($promptOrder)) {
                $prompts = [];
                foreach ($promptOrder as $promptName) {
                    $file = $promptsPath . '/' . $promptName . '.txt';
                    if (File::exists($file)) {
                        $prompts[] = trim(File::get($file));
                    }
                }
                if (!empty($prompts)) {
                    return implode("\n\n", $prompts);
                }
            }

            // Fallback to alphabetical sorting if no order specified
            $files = File::glob($promptsPath . '/*.txt');
            sort($files); 
            
            $prompts = [];
            foreach ($files as $file) {
                $prompts[] = trim(File::get($file));
            }
            
            if (!empty($prompts)) {
                return implode("\n\n", $prompts);
            }
        }

        // Fallback to legacy single file or config
        $legacyPath = base_path('training/system_prompt.txt');
        if (File::exists($legacyPath)) {
            return trim(File::get($legacyPath));
        }

        return config('services.ollama.system_prompt', 'You are a professional company assistant.');
    }
}
