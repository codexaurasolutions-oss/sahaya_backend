<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\TranscriptionResponse;
use RuntimeException;
use Throwable;

class VoiceTranscriptionService
{
    private const TRANSCRIPTION_PROMPT = 'Transcribe the spoken search query in its original language. English speech must stay in English using Latin letters. Hindi, Urdu, Hindustani, Hinglish, or Roman Urdu speech must be written as natural Hindi in Devanagari. Telugu speech must stay in Telugu script. Never use Urdu or Arabic script. Preserve names, locations, numbers, and job roles accurately.';

    private const HINDI_TRANSCRIPTION_PROMPT = 'बोली गई बात को स्वाभाविक हिंदी देवनागरी में लिखें। नाम, स्थान, संख्या और नौकरी की भूमिका सही रखें। उर्दू या अरबी लिपि का उपयोग न करें।';

    private const NORMALIZATION_PROMPT = <<<'PROMPT'
Normalize a short voice-search transcript and return only a JSON object with keys "text" and "language".

Rules:
- Genuine English must remain English in Latin script. Never translate English into Hindi.
- Hindi, Urdu, Hindustani, Hinglish, and Roman Urdu must be returned as natural Hindi in Devanagari script with language "hi".
- Telugu must remain in Telugu script with language "te".
- Never return Urdu or Arabic script.
- Preserve names, locations, numbers, salaries, and job roles accurately.
- For any other language, preserve the transcript and return its ISO 639-1 language code when known.
PROMPT;

    private const SUPPORTED_EXTENSIONS = [
        'm4a',
        'mp3',
        'mpeg',
        'mpga',
        'ogg',
        'wav',
        'webm',
    ];

    public function transcribe(
        string $audioPath,
        ?string $language = null,
        ?string $originalExtension = null
    ): array
    {
        $extension = strtolower(ltrim((string) $originalExtension, '.'));

        // Android MediaRecorder writes AAC audio in an MPEG-4 container. Naming
        // that upload .m4a lets the transcription API identify it as audio.
        if ($extension === 'mp4') {
            $extension = 'm4a';
        }

        if (! in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            $extension = 'm4a';
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'sahayya-voice-');
        if ($temporaryBase === false) {
            throw new RuntimeException('A temporary audio file could not be created.');
        }

        $namedAudioPath = $temporaryBase.'.'.$extension;
        if (! rename($temporaryBase, $namedAudioPath) || ! copy($audioPath, $namedAudioPath)) {
            @unlink($temporaryBase);
            @unlink($namedAudioPath);
            throw new RuntimeException('The uploaded audio could not be prepared.');
        }

        try {
            $requestedLanguage = $this->normalizeLanguageCode($language);
            $response = $this->requestTranscription(
                $namedAudioPath,
                $requestedLanguage,
                self::TRANSCRIPTION_PROMPT,
            );
            $normalized = $this->normalizeTranscript(
                $this->validatedTranscriptionText($response),
                $response->language ?: $requestedLanguage,
            );

            if ($this->containsArabicScript($normalized['text'])) {
                $hindiResponse = $this->requestTranscription(
                    $namedAudioPath,
                    'hi',
                    self::HINDI_TRANSCRIPTION_PROMPT,
                );
                $normalized = $this->normalizeTranscript(
                    $this->validatedTranscriptionText($hindiResponse),
                    'hi',
                );
            }

            if ($this->containsArabicScript($normalized['text'])) {
                throw new RuntimeException('The transcript could not be converted from Urdu script.');
            }

            return [
                'text' => $normalized['text'],
                'language' => $normalized['language'],
            ];
        } finally {
            @unlink($namedAudioPath);
        }
    }

    private function requestTranscription(
        string $audioPath,
        ?string $language,
        string $prompt
    ): TranscriptionResponse {
        $stream = fopen($audioPath, 'rb');

        if ($stream === false) {
            throw new RuntimeException('The uploaded audio could not be read.');
        }

        try {
            $parameters = [
                'model' => config('openai.transcription_model', 'gpt-4o-mini-transcribe'),
                'file' => $stream,
                'prompt' => $prompt,
                'temperature' => 0,
            ];

            if ($language !== null) {
                $parameters['language'] = $language;
            }

            return OpenAI::audio()->transcribe($parameters);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function normalizeTranscript(string $text, ?string $detectedLanguage): array
    {
        if ($text === '') {
            return [
                'text' => '',
                'language' => $this->normalizeLanguageCode($detectedLanguage),
            ];
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => config('openai.transcript_normalizer_model', 'gpt-4o-mini'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => self::NORMALIZATION_PROMPT,
                    ],
                    [
                        'role' => 'user',
                        'content' => json_encode([
                            'transcript' => $text,
                            'detected_language' => $detectedLanguage,
                        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);

            $decoded = json_decode(
                (string) $response->choices[0]->message->content,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
            $normalizedText = trim((string) ($decoded['text'] ?? ''));

            if ($normalizedText === '') {
                throw new RuntimeException('The transcript normalizer returned empty text.');
            }

            return [
                'text' => $normalizedText,
                'language' => $this->normalizeLanguageCode(
                    (string) ($decoded['language'] ?? $detectedLanguage)
                ),
            ];
        } catch (Throwable $exception) {
            Log::warning('Voice transcript language normalization failed', [
                'message' => $exception->getMessage(),
                'detected_language' => $detectedLanguage,
            ]);

            return [
                'text' => $text,
                'language' => $this->normalizeLanguageCode($detectedLanguage),
            ];
        }
    }

    private function validatedTranscriptionText(TranscriptionResponse $response): string
    {
        $text = trim($response->text);
        $decoded = json_decode($text, true);

        if (is_array($decoded) && isset($decoded['error'])) {
            throw new RuntimeException('The transcription provider rejected the audio request.');
        }

        return $text;
    }

    private function normalizeLanguageCode(?string $language): ?string
    {
        $language = strtolower(trim((string) $language));

        return match ($language) {
            '' => null,
            'en', 'eng', 'english' => 'en',
            'hi', 'hin', 'hindi', 'ur', 'urd', 'urdu' => 'hi',
            'te', 'tel', 'telugu' => 'te',
            default => $language,
        };
    }

    private function containsArabicScript(string $text): bool
    {
        return preg_match('/\p{Arabic}/u', $text) === 1;
    }
}
