<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TextToSpeechController extends Controller
{
    /**
     * Generate speech from text with natural verbal flow and intonations.
     */
    public function speech(Request $request)
    {
        $request->validate([
            'input' => 'required|string',
            'voice' => 'required|string',
            'response_format' => 'nullable|string|in:mp3,wav,flac,pcm',
            'speed' => 'nullable|numeric|min:0.25|max:4.0',
            'auto_natural_flow' => 'nullable|boolean',
        ]);

        $baseUrl = config('services.tts.url', 'http://localhost:8880');
        $defaultSpeed = config('services.tts.speed', 0.95);
        $shouldPreprocess = $request->input('auto_natural_flow', config('services.tts.auto_natural_flow', true));

        $inputText = $request->input('input');
        if ($shouldPreprocess) {
            $inputText = $this->preprocessTextForNaturalFlow($inputText);
        }

        $format = $request->input('response_format', 'mp3');
        $contentTypes = [
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'pcm' => 'audio/l16',
        ];
        $contentType = $contentTypes[$format] ?? 'audio/mpeg';

        $response = Http::withOptions([
            'stream' => true,
        ])->post("{$baseUrl}/v1/audio/speech", [
            'model' => 'kokoro',
            'input' => $inputText,
            'voice' => $request->input('voice'),
            'response_format' => $format,
            'speed' => (float) $request->input('speed', $defaultSpeed),
        ]);

        if ($response->failed()) {
            return response()->json([
                'error' => 'Failed to generate speech',
            ], 500);
        }

        return response()->stream(function () use ($response) {
            $stream = $response->toPsrResponse()->getBody();
            while (!$stream->eof()) {
                echo $stream->read(1024);
                ob_flush();
                flush();
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="speech.' . $format . '"',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * List available voices.
     */
    public function voices()
    {
        $baseUrl = config('services.tts.url', 'http://localhost:8880');

        $response = Http::get("{$baseUrl}/v1/audio/voices");

        if ($response->failed()) {
            return response()->json(['error' => 'Failed to fetch voices'], $response->status());
        }

        return response()->json($response->json());
    }

    /**
     * Preprocess text input to optimize verbal flow, intonation, and pauses for Kokoro TTS.
     */
    private function preprocessTextForNaturalFlow(string $text): string
    {
        // 1. Decode any HTML entities if present
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Standardize ellipses and multiple dots to create medium-long natural pauses
        $text = preg_replace('/\.{2,}/u', '... ', $text);
        $text = preg_replace('/…/u', '... ', $text);

        // 3. Convert long dashes/hyphens to a natural pause (comma or ellipsis)
        $text = preg_replace('/\s*[—–-]{2,}\s*/u', '... ', $text);
        $text = preg_replace('/\s+-\s+/u', ', ', $text);

        // 4. Ensure there's a space after every punctuation mark so Kokoro handles the boundary pause properly
        $text = preg_replace('/,([^ 0-9a-zA-Z\.])/u', ', $1', $text);
        $text = preg_replace('/\.([^ 0-9a-zA-Z\.])/u', '. $1', $text);
        $text = preg_replace('/;([^ ])/u', '; $1', $text);
        $text = preg_replace('/:([^ ])/u', ': $1', $text);
        $text = preg_replace('/\?([^ ])/u', '? $1', $text);
        $text = preg_replace('/!([^ ])/u', '! $1', $text);

        // 5. Structure lists/bullet points and headings for conversational pacing
        $lines = explode("\n", $text);
        $processedLines = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // If it's a bullet or numbered list item
            if (preg_match('/^([*\-•+]|\d+\.)\s+(.+)$/u', $trimmed, $matches)) {
                $content = $matches[2];
                // Ensure it ends with proper punctuation
                if (!preg_match('/[.,;:!?]$/u', $content)) {
                    $content .= ','; // Add a comma for a natural pause between list items
                }
                $processedLines[] = '... ' . $content;
            } else {
                $processedLines[] = $trimmed;
            }
        }
        $text = implode(' ', $processedLines);

        // 6. Sentence boundary conditioning
        // Split long clauses (> 18 words) that don't have natural pause punctuation by adding commas.
        $sentences = preg_split('/(?<=[.!?])\s+/u', $text);
        $conditionedSentences = [];
        foreach ($sentences as $sentence) {
            $words = explode(' ', $sentence);
            if (count($words) > 18 && !str_contains($sentence, ',')) {
                // Find a logical place to insert a comma (like before transition/conjunction words)
                $inserted = false;
                for ($i = (int)(count($words) / 2); $i < count($words) - 3; $i++) {
                    if (in_array(strtolower($words[$i]), ['and', 'but', 'or', 'which', 'while', 'although', 'because', 'who', 'when', 'where', 'na', 'at', 'dahil', 'sapagkat'])) {
                        $words[$i] = ', ' . $words[$i];
                        $inserted = true;
                        break;
                    }
                }
                if (!$inserted) {
                    $mid = (int)(count($words) / 2);
                    if (isset($words[$mid])) {
                        $words[$mid] .= ',';
                    }
                }
                $sentence = implode(' ', $words);
            }
            $conditionedSentences[] = $sentence;
        }
        $text = implode(' ', $conditionedSentences);

        // 7. Inject conversational pauses after introductory words
        $introductoryWords = ['hello', 'hi', 'sure', 'yes', 'no', 'indeed', 'absolutely', 'of course', 'kamusta', 'oo', 'hindi', 'syempre'];
        foreach ($introductoryWords as $word) {
            $text = preg_replace('/\b(' . preg_quote($word, '/') . ')\b(?!\s*[,.!?])/ui', '$1,', $text);
        }

        // 8. Clean up double spaces or consecutive punctuation
        $text = preg_replace('/\s{2,}/u', ' ', $text);
        $text = preg_replace('/,\s*,/u', ',', $text);
        $text = preg_replace('/\.\s*\./u', '.', $text);
        $text = trim($text);

        return $text;
    }
}
