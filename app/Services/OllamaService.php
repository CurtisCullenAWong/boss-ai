<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class OllamaService
{
    protected const OUT_OF_SCOPE_REFUSAL_EN = 'I am the Boss Cargo Express AI. I only assist with inquiries related to our company and logistics services. For other matters, please contact info@bosscargo.express.';
    protected const OUT_OF_SCOPE_REFUSAL_FIL = 'Ako ang Boss Cargo Express AI. Tumutulong lamang ako sa mga tanong tungkol sa aming kumpanya at serbisyo sa logistika. Para sa ibang usapin, mangyaring mag-email sa info@bosscargo.express.';
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
        if (isset($this->options['num_thread'])) {
            $this->options['num_thread'] = (int) $this->options['num_thread'];
        }
        if (isset($this->options['num_gpu'])) {
            $this->options['num_gpu'] = (int) $this->options['num_gpu'];
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
            // Always call the API (do not cache) so each generation can differ.
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
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (generate): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Quick scope filter to avoid expensive model calls for unrelated prompts.
     */
    public function isInScopePrompt(string $prompt): bool
    {
        $p = strtolower(trim($prompt));

        if ($p === '') {
            return false;
        }

        // Build or fetch training-derived vocabulary (cached)
        $vocab = $this->buildScopeVocabulary();

        // Tokenize prompt into normalized words and bigrams
        $promptTokens = $this->tokenizeText($p);

        if (empty($promptTokens)) {
            return false;
        }

        // Count overlaps with the training vocabulary
        $overlap = 0;
        foreach ($promptTokens as $t) {
            if (isset($vocab[$t])) {
                $overlap++;
            }
        }

        $promptTokenCount = count($promptTokens);

        // If there's any direct overlap with training data, allow the prompt.
        if ($overlap > 0) {
            return true;
        }

        // For longer prompts with zero overlap, treat as out-of-scope to avoid costly generations.
        if ($promptTokenCount >= 5) {
            return false;
        }

        // Short prompts with no overlap: allow (likely ambiguous/short queries).
        return true;
    }

    /**
     * Return the standardized out-of-scope refusal in the user's language.
     */
    public function outOfScopeRefusal(string $prompt): string
    {
        return $this->isLikelyFilipino($prompt)
            ? self::OUT_OF_SCOPE_REFUSAL_FIL
            : self::OUT_OF_SCOPE_REFUSAL_EN;
    }

    /**
     * Compile knowledge base from training data files.
     */
    public function compileKnowledgeBase(): string
    {
        $knowledgeBase = [];
        $sourcePaths = $this->resolveKnowledgeBaseSources();

        foreach ($sourcePaths as $sourcePath) {
            if (!File::exists($sourcePath)) {
                Log::warning("Knowledge base source file not found: {$sourcePath}");
                continue;
            }

            $content = trim(File::get($sourcePath));

            if ($content === '') {
                continue;
            }

            // Remove obsolete source references and directory paths from the content to keep the knowledge base clean
            $content = preg_replace('/\s*\(source: \[.*?\]\(.*?\/datasets\/raw\/.*?\)\)/i', '', $content);
            $content = preg_replace('/\s*\(source: .*?\/datasets\/raw\/.*?\)/i', '', $content);

            $knowledgeBase[] = '### ' . $this->relativeTrainingPath($sourcePath) . " ###\n" . trim($content);
        }

        $compiledKnowledgeBase = trim(implode("\n\n", $knowledgeBase));

        // Save reference files for debugging and review
        File::put(base_path('training/full_knowledge_base.txt'), $compiledKnowledgeBase);

        return $compiledKnowledgeBase;
    }

    /**
     * Train a new model based on the current configuration and training data.
     */
    public function train(string $modelName, ?string $baseModel = null): ?array
    {
        $baseModel = $baseModel ?? config('services.ollama.base_model');
        
        // Ensure base model is available locally before creating the derived model.
        if (!$this->hasLocalModel($baseModel)) {
            Log::info("Base model not found locally; pulling model: {$baseModel}");
            $this->pullModel($baseModel);
        }

        $knowledgeBase = $this->compileKnowledgeBase();

        $modelfileContent = $this->buildModelfileContent($baseModel, $knowledgeBase);

        // Save reference Modelfile for debugging and review
        File::put(base_path('training/Modelfile'), $modelfileContent);

        return $this->createModel($modelName, $modelfileContent, $baseModel);
    }

    /**
     * Determine whether a model is already available locally.
     */
    public function hasLocalModel(string $name): bool
    {
        $models = $this->listModels();

        if (!is_array($models) || !isset($models['models']) || !is_array($models['models'])) {
            return false;
        }

        foreach ($models['models'] as $model) {
            if (is_array($model) && ($model['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
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
     * Delete a local model.
     */
    public function deleteModel(string $name): bool
    {
        try {
            $response = Http::delete("{$this->baseUrl}/api/delete", [
                'name' => $name,
            ]);

            if ($response->failed()) {
                Log::error('Ollama API deleteModel failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'name' => $name,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Ollama Service Error (deleteModel): ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load the system prompt from the modular prompts directory.
     */
    protected function loadSystemPrompt(): string
    {
        $promptsPath = base_path('training/prompts');

        if (File::isDirectory($promptsPath)) {
            $config = $this->loadTrainingConfig();
            $promptOrder = $config['training_options']['prompt_order'] ?? [];
            $promptFiles = [];
            $seenFiles = [];

            foreach ($promptOrder as $promptName) {
                $file = $promptsPath . '/' . $promptName;

                if (!str_ends_with($file, '.txt')) {
                    $file .= '.txt';
                }

                if (File::exists($file)) {
                    $promptFiles[] = $file;
                    $seenFiles[realpath($file) ?: $file] = true;
                }
            }

            $files = File::files($promptsPath);
            usort($files, function ($left, $right) {
                return strcmp($left->getFilename(), $right->getFilename());
            });

            foreach ($files as $file) {
                if ($file->getExtension() !== 'txt') {
                    continue;
                }

                $path = $file->getPathname();
                $key = realpath($path) ?: $path;

                if (isset($seenFiles[$key])) {
                    continue;
                }

                $promptFiles[] = $path;
                $seenFiles[$key] = true;
            }

            $prompts = [];
            foreach ($promptFiles as $file) {
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

    /**
     * Load the training configuration from the training directory.
     */
    protected function loadTrainingConfig(): array
    {
        $configPath = base_path('training/configs/model_config.json');

        if (!File::exists($configPath)) {
            return [];
        }

        $config = json_decode(File::get($configPath), true);

        return is_array($config) ? $config : [];
    }

    /**
     * Build a Modelfile from the base model, configuration, and compiled knowledge base.
     */
    protected function buildModelfileContent(string $baseModel, string $knowledgeBase): string
    {
        $parameters = $this->options;

        $modelfileContent = "FROM {$baseModel}\n";

        foreach ($this->formatModelfileParameters($parameters) as $parameterLine) {
            $modelfileContent .= $parameterLine . "\n";
        }

        $modelfileContent .= "SYSTEM \"\"\"\n" .
            trim($this->systemPrompt) . "\n\n" .
            "KNOWLEDGE BASE:\n" .
            $knowledgeBase . "\n\n" .
            "Always use the provided knowledge base to answer questions.\n" .
            "\"\"\"";

        return $modelfileContent;
    }

    /**
     * Format Modelfile parameters, expanding arrays such as stop sequences.
     */
    protected function formatModelfileParameters(array $parameters): array
    {
        $lines = [];

        foreach ($parameters as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }

                    $lines[] = 'PARAMETER ' . $key . ' ' . $this->formatModelfileValue($item);
                }

                continue;
            }

            $lines[] = 'PARAMETER ' . $key . ' ' . $this->formatModelfileValue($value);
        }

        return $lines;
    }

    /**
     * Resolve a value into Modelfile syntax.
     */
    protected function formatModelfileValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return (string) $value;
        }

        return '"' . str_replace('"', '\\"', (string) $value) . '"';
    }

    /**
     * Resolve the training knowledge base source files.
     */
    protected function resolveKnowledgeBaseSources(): array
    {
        $sourcePaths = [];
        $allowedSourcesPath = base_path('training/allowed_sources.json');

        if (File::exists($allowedSourcesPath)) {
            $allowedSources = json_decode(File::get($allowedSourcesPath), true);

            if (isset($allowedSources['allowed_paths']) && is_array($allowedSources['allowed_paths'])) {
                foreach ($allowedSources['allowed_paths'] as $path) {
                    $resolvedPath = $this->resolveTrainingPath($path);
                    
                    // Skip obsolete directories even if listed in allowed_sources
                    $normalized = str_replace('\\', '/', $resolvedPath);
                    if (str_contains($normalized, '/datasets/raw/') || str_contains($normalized, '/datasets/validation/')) {
                        continue;
                    }
                    
                    $sourcePaths[] = $resolvedPath;
                }

                return array_values(array_unique($sourcePaths));
            }
        }

        // If no allowed_sources.json is provided, include all useful files
        // under the training directory so `ai:train` uses the entire tree.
        $trainingPath = base_path('training');

        if (File::isDirectory($trainingPath)) {
            $allowedExts = ['txt', 'md', 'json', 'jsonl', 'csv', 'yaml', 'yml'];

            foreach (File::allFiles($trainingPath) as $file) {
                $path = $file->getPathname();
                $normalized = str_replace('\\', '/', $path);
                
                // Skip obsolete directories
                if (str_contains($normalized, '/datasets/raw/') || str_contains($normalized, '/datasets/validation/')) {
                    continue;
                }

                $ext = strtolower($file->getExtension());

                if ($ext === '') {
                    // include files without extension only if they are named like full_knowledge_base or similar
                    $name = $file->getFilename();
                    if (in_array($name, ['full_knowledge_base.txt', 'Modelfile', 'full_system_prompt.txt']) || str_ends_with($name, '.txt')) {
                        $sourcePaths[] = $path;
                    }

                    continue;
                }

                if (in_array($ext, $allowedExts, true)) {
                    $sourcePaths[] = $path;
                }
            }
        }

        return array_values(array_unique($sourcePaths));
    }

    /**
     * Normalize a training-relative path into an absolute path.
     */
    protected function resolveTrainingPath(string $path): string
    {
        $normalizedPath = ltrim($path, '/');

        if (str_starts_with($normalizedPath, 'training/')) {
            return base_path($normalizedPath);
        }

        return base_path('training/' . $normalizedPath);
    }

    /**
     * Convert an absolute path into a training-relative display path.
     */
    protected function relativeTrainingPath(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * Lightweight language hinting for refusal localization.
     */
    protected function isLikelyFilipino(string $prompt): bool
    {
        return (bool) preg_match('/\b(ano|paano|saan|kailan|magkano|serbisyo|kumpanya|trabaho|aplikasyon|logistika|mangyaring|tungkol)\b/i', $prompt);
    }

    /**
     * Build a vocabulary (words + bigrams) derived from training data.
     * Cached to avoid repeated expensive parsing.
     */
    protected function buildScopeVocabulary(): array
    {
        return Cache::remember('ollama_scope_vocab', 86400, function () {
            $text = $this->compileKnowledgeBase();

            // Also include modular prompts
            $promptsPath = base_path('training/prompts');
            if (File::isDirectory($promptsPath)) {
                foreach (File::files($promptsPath) as $f) {
                    $text .= "\n" . File::get($f->getPathname());
                }
            }

            $tokens = $this->tokenizeText($text);

            // Build a set for fast lookup
            $set = [];
            foreach ($tokens as $t) {
                $set[$t] = true;
            }

            return $set;
        });
    }

    /**
     * Tokenize text into normalized unigrams and bigrams.
     */
    protected function tokenizeText(string $text): array
    {
        $text = strtolower($text);

        // Replace non-alphanumeric with spaces
        $clean = preg_replace('/[^a-z0-9]+/i', ' ', $text);
        $words = array_values(array_filter(array_map('trim', explode(' ', $clean)), function ($w) {
            return $w !== '' && strlen($w) >= 3;
        }));

        $tokens = [];

        // add unigrams
        foreach ($words as $w) {
            $tokens[] = $w;
        }

        // add bigrams
        for ($i = 0, $len = count($words); $i + 1 < $len; $i++) {
            $tokens[] = $words[$i] . ' ' . $words[$i + 1];
        }

        // return unique tokens
        return array_values(array_unique($tokens));
    }
}
