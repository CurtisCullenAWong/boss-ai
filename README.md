# Boss AI - Ollama Laravel Controller with Kokoro Text-to-Speech

Boss AI is a Laravel-based controller and intelligent assistant designed for Boss Cargo Express. It integrates local Large Language Models (LLMs) via Ollama and realistic, low-latency audio generation via a containerized Kokoro Text-to-Speech (TTS) service.

## Features

- **Local LLM Integration**: Uses Ollama to run models locally, ensuring data privacy and low latency.
- **Kokoro Text-to-Speech**: Seamlessly streams high-quality, realistic audio generation on CPU with low latency.
- **Dynamic Audio Streaming**: Leverages Symfony streamed responses to pipe audio chunks to the client immediately as they are generated.
- **Custom Knowledge Base**: Trained on Boss Cargo Express specific data.
- **Behavioral Guardrails**: Strict scope enforcement for logistics and customer support queries.
- **Inherent Knowledge**: Responses feel natural and integrated, avoiding mentions of underlying knowledge bases or training files.
- **Agentic Workflow**: Built with Laravel conventions for robust AI and speech interactions.
- **Dockerized Environment**: Fully containerized using Laravel Sail, including Ollama and Kokoro TTS.

## Prerequisites

Before you begin, ensure you have the following installed:

- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [PHP 8.2+](https://www.php.net/) (for local development outside Docker)
- [Composer](https://getcomposer.org/)
- [Node.js & NPM](https://nodejs.org/)

## Getting Started

### 1. Clone the Repository

```bash
git clone <repository-url>
cd boss-ai
```

### 2. Environment Setup

Copy the example environment file and configure your variables:

```bash
cp .env.example .env
```

**Important Environment Variables:**

| Variable | Description | Default |
|----------|-------------|---------|
| `OLLAMA_URL` | The URL of your Ollama instance | `http://ollama:11434` |
| `OLLAMA_BASE_MODEL` | The base model to use for training/inference | `llama3.2:3b` |
| `OLLAMA_MODEL` | The name of your custom trained model | `company-chatbot` |
| `TTS_URL` | The URL of your Kokoro TTS service | `http://host.docker.internal:8880` |
| `TTS_SPEED` | Default speed multiplier for speech synthesis | `0.95` |
| `TTS_AUTO_NATURAL_FLOW` | Auto-conditioning of input text for pauses/intonation | `true` |

### 3. Install Dependencies

Using Laravel Sail (Docker):

```bash
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -v "$(pwd):/var/www/html" \
    -w /var/www/html \
    laravelsail/php82-composer:latest \
    composer install --ignore-platform-reqs
```

### 4. Start the Application

```bash
./vendor/bin/sail up -d
```

### 5. Generate Application Key

```bash
./vendor/bin/sail artisan key:generate
```

### 6. Run Migrations

```bash
./vendor/bin/sail artisan migrate
```

### 7. Compile Assets

```bash
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

## AI Model & TTS Setup

Boss AI uses specific commands and API routes to manage local models, handle TTS requests, and enforce behavioral guardrails.

### Pull the Base Model
Ensure your Ollama instance has the required base model:
```bash
./vendor/bin/sail artisan ai:pull
```

### Train/Configure the Custom Model
Initialize the custom model with the company knowledge base and behavioral rules:
```bash
./vendor/bin/sail artisan ai:train
```

### Text-to-Speech Endpoints

The Ollama Laravel controller exposes API routes to interact with Kokoro TTS:
- **POST `/api/tts/speech`**: Streams synthesized audio chunks from a given text input.
  - Payload options: `input` (text), `voice` (e.g. `af_heart`), `response_format` (`mp3`, `wav`, `flac`, `pcm`), `speed`, `auto_natural_flow`.
- **GET `/api/tts/voices`**: Returns all available voice packs.

### Behavioral Guardrails
The AI is configured to:
- Only answer questions related to Boss Cargo Express or logistics support.
- Provide minimal effort/refusals for irrelevant queries.
- Act as if its knowledge is inherent, never mentioning source files or training data.

### List Available Models
Check the status of models in your Ollama instance:
```bash
./vendor/bin/sail artisan ai:list
```

## Project Structure

- `app/Console/Commands`: Contains AI-related artisan commands (`ai:pull`, `ai:train`, etc.)
- `app/Http/Controllers/Api`: Controller endpoints managing Ollama chat models and Kokoro TTS speech streaming.
- `training/`: Directory containing source data for model training.
- `compose.yaml`: Docker configuration including PHP, Ollama, and Kokoro TTS CPU.

## License

The Boss AI project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
