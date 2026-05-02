# Boss Cargo Express - Chatbot Training
This directory contains the source data, prompts, and configuration files for the Boss Cargo Express AI model.

## Directory Structure
- `datasets/processed/compressed_kb.md`: Canonical merged knowledge base.
- `datasets/processed/extracted_dates.md`: Supporting timeline extraction.
- `prompts/`: Modular system prompts (Persona, Safety, etc.).
- `datasets/`: Raw and processed training sets.
- `configs/`: Model and training configuration.

## Training Policy
- Use `datasets/processed/compressed_kb.md` as the single merged source for company, services, FAQ, and contact data.
- Keep legacy source files only as pointers when provenance is required.
