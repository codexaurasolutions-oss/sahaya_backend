<?php

namespace Tests\Unit;

use App\Services\VoiceTranscriptionService;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response as HttpResponse;
use OpenAI as OpenAIClient;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Audio\TranscriptionResponse;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class VoiceTranscriptionServiceTest extends TestCase
{
    public function test_it_transcribes_audio_through_the_backend_openai_client(): void
    {
        config(['openai.transcription_model' => 'gpt-4o-mini-transcribe']);

        $response = TranscriptionResponse::fake([
            'text' => 'driver in Karachi',
            'language' => 'en',
        ]);
        $fake = OpenAI::fake([
            $response,
            $this->normalizationResponse('driver in Karachi', 'en'),
        ]);
        $audioPath = tempnam(sys_get_temp_dir(), 'voice-test-');
        file_put_contents($audioPath, 'fake-audio');

        try {
            $result = (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                null,
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }

        $this->assertSame('driver in Karachi', $result['text']);
        $this->assertSame('en', $result['language']);
        $fake->audio()->assertSent(function ($method, $parameters) {
            return $method === 'transcribe'
                && $parameters['model'] === 'gpt-4o-mini-transcribe'
                && array_key_exists('file', $parameters)
                && str_contains($parameters['prompt'], 'English speech must stay in English')
                && $parameters['temperature'] === 0
                && ! array_key_exists('language', $parameters);
        });
        $fake->audio()->assertSent(1);
        $fake->chat()->assertSent(function ($method, $parameters) {
            return $method === 'create'
                && $parameters['model'] === 'gpt-4o-mini'
                && $parameters['temperature'] === 0
                && $parameters['response_format'] === ['type' => 'json_object'];
        });
    }

    public function test_it_converts_roman_urdu_into_hindi_devanagari(): void
    {
        $response = TranscriptionResponse::fake([
            'text' => 'mujhe Karachi mein driver chahiye',
            'language' => null,
        ]);
        $fake = OpenAI::fake([
            $response,
            $this->normalizationResponse('मुझे कराची में ड्राइवर चाहिए', 'hi'),
        ]);
        $audioPath = tempnam(sys_get_temp_dir(), 'voice-test-');
        file_put_contents($audioPath, 'fake-audio');

        try {
            $result = (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                null,
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }

        $this->assertSame('मुझे कराची में ड्राइवर चाहिए', $result['text']);
        $this->assertSame('hi', $result['language']);
        $fake->audio()->assertSent(1);
        $fake->chat()->assertSent(1);
    }

    public function test_it_never_sends_urdu_as_the_requested_transcription_language(): void
    {
        $response = TranscriptionResponse::fake([
            'text' => 'मुझे ड्राइवर चाहिए',
            'language' => 'hi',
        ]);
        $fake = OpenAI::fake([
            $response,
            $this->normalizationResponse('मुझे ड्राइवर चाहिए', 'hi'),
        ]);
        $audioPath = tempnam(sys_get_temp_dir(), 'voice-test-');
        file_put_contents($audioPath, 'fake-audio');

        try {
            $result = (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                'ur',
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }

        $this->assertSame('hi', $result['language']);
        $fake->audio()->assertSent(function ($method, $parameters) {
            return $method === 'transcribe'
                && $parameters['language'] === 'hi';
        });
    }

    public function test_it_retries_in_hindi_if_urdu_script_is_returned(): void
    {
        $fake = OpenAI::fake([
            TranscriptionResponse::fake([
                'text' => 'مجھے ڈرائیور چاہیے',
                'language' => 'ur',
            ]),
            $this->normalizationResponse('مجھے ڈرائیور چاہیے', 'ur'),
            TranscriptionResponse::fake([
                'text' => 'मुझे ड्राइवर चाहिए',
                'language' => 'hi',
            ]),
            $this->normalizationResponse('मुझे ड्राइवर चाहिए', 'hi'),
        ]);
        $audioPath = tempnam(sys_get_temp_dir(), 'voice-test-');
        file_put_contents($audioPath, 'fake-audio');

        try {
            $result = (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                null,
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }

        $this->assertSame('मुझे ड्राइवर चाहिए', $result['text']);
        $this->assertSame('hi', $result['language']);
        $fake->audio()->assertSent(2);
        $fake->chat()->assertSent(2);
        $fake->audio()->assertSent(function ($method, $parameters) {
            return $method === 'transcribe'
                && ($parameters['language'] ?? null) === 'hi'
                && str_contains($parameters['prompt'], 'देवनागरी');
        });
    }

    public function test_it_preserves_the_original_transport_error_when_the_client_closes_the_stream(): void
    {
        OpenAI::fake([new \RuntimeException('transport failed')]);
        $audioPath = tempnam(sys_get_temp_dir(), 'voice-test-');
        file_put_contents($audioPath, 'fake-audio');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('transport failed');
            (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                null,
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }
    }

    public function test_it_rejects_an_api_error_payload_instead_of_returning_it_as_transcript_text(): void
    {
        OpenAI::fake([
            TranscriptionResponse::fake([
                'text' => '{"error":{"message":"invalid key"},"status":401}',
                'language' => null,
            ]),
        ]);
        $audioPath = tempnam(sys_get_temp_dir(), 'voice-test-');
        file_put_contents($audioPath, 'fake-audio');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('transcription provider rejected');
            (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                null,
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }
    }

    public function test_it_sends_android_mp4_audio_with_an_m4a_multipart_filename(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode(['text' => 'driver in Karachi', 'language' => 'en']),
            ),
            new HttpResponse(
                200,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'id' => 'chatcmpl-voice-test',
                    'object' => 'chat.completion',
                    'created' => 1_700_000_000,
                    'model' => 'gpt-4o-mini',
                    'system_fingerprint' => null,
                    'choices' => [[
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => '{"text":"driver in Karachi","language":"en"}',
                            'function_call' => null,
                            'tool_calls' => [],
                        ],
                        'finish_reason' => 'stop',
                    ]],
                    'usage' => [
                        'prompt_tokens' => 10,
                        'completion_tokens' => 8,
                        'total_tokens' => 18,
                    ],
                ]),
            ),
        ]));
        $stack->push(Middleware::history($history));
        $client = OpenAIClient::factory()
            ->withApiKey('test-key')
            ->withHttpClient(new HttpClient(['handler' => $stack]))
            ->make();
        OpenAI::swap($client);

        $audioPath = tempnam(sys_get_temp_dir(), 'voice-upload-');
        file_put_contents($audioPath, 'fake-mpeg-4-audio');

        try {
            $result = (new VoiceTranscriptionService())->transcribe(
                $audioPath,
                null,
                'mp4',
            );
        } finally {
            @unlink($audioPath);
        }

        $this->assertSame('driver in Karachi', $result['text']);
        $this->assertCount(2, $history);
        $request = $history[0]['request'];
        $multipartBody = (string) $request->getBody();

        $this->assertStringContainsString('multipart/form-data', $request->getHeaderLine('Content-Type'));
        $this->assertMatchesRegularExpression(
            '/filename="[^"]+\.m4a"/',
            $multipartBody,
        );
        $this->assertStringContainsString('Content-Type: audio/mp4', $multipartBody);
        $this->assertStringContainsString('name="prompt"', $multipartBody);
    }

    private function normalizationResponse(string $text, string $language): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'text' => $text,
                            'language' => $language,
                        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    ],
                ],
            ],
        ]);
    }
}
