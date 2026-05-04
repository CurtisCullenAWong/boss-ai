# Boss AI - Intelligent Cargo Assistant

Boss AI is a Laravel-based intelligent assistant designed for Boss Cargo Express. It leverages local Large Language Models (LLMs) via Ollama to provide accurate information about company services, history, and operations.

## 🚀 Features

- **Local LLM Integration**: Uses Ollama to run models locally, ensuring data privacy and low latency.
- **Custom Knowledge Base**: Trained on Boss Cargo Express specific data.
- **Agentic Workflow**: Built with Laravel conventions for robust AI interactions.
- **Dockerized Environment**: Fully containerized using Laravel Sail.

## 🛠 Prerequisites

Before you begin, ensure you have the following installed:

- [Docker Desktop](https://www.docker.com/products/docker-desktop)
- [PHP 8.2+](https://www.php.net/) (for local development outside Docker)
- [Composer](https://getcomposer.org/)
- [Node.js & NPM](https://nodejs.org/)

## 📥 Getting Started

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
| `OLLAMA_SYSTEM_PROMPT` | The identity and constraints for the AI | (See .env) |

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

## 🤖 AI Model Setup

Boss AI uses specific commands to manage the local models.

### Pull the Base Model
Ensure your Ollama instance has the required base model:
```bash
./vendor/bin/sail artisan ai:pull
```

### Train/Configure the Custom Model
Initialize the custom model with the company knowledge base:
```bash
./vendor/bin/sail artisan ai:train
```

### List Available Models
Check the status of models in your Ollama instance:
```bash
./vendor/bin/sail artisan ai:list
```

## 📂 Project Structure

- `app/Console/Commands`: Contains AI-related artisan commands (`ai:pull`, `ai:train`, etc.)
- `training/`: Directory containing source data for model training.
- `compose.yaml`: Docker configuration including PHP, MySQL, and Ollama.

## 📄 License

The Boss AI project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
