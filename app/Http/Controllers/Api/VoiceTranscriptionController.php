<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class VoiceTranscriptionController extends Controller
{
    public function __construct(
        private readonly VoiceTranscriptionService $transcriptionService
    ) {
    }

    public function transcribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:10240', 'mimes:mp3,mp4,m4a,mpeg,mpga,wav,ogg,webm'],
            'language' => ['nullable', 'string', 'regex:/^[a-z]{2,3}$/i'],
        ]);

        $audio = $request->file('audio');

        try {
            $result = $this->transcriptionService->transcribe(
                $audio->getRealPath(),
                $validated['language'] ?? null,
                $audio->getClientOriginalExtension(),
            );

            if ($result['text'] === '') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No clear speech was detected. Please try again.',
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Voice transcription failed', [
                'user_id' => $request->user()?->id,
                'message' => $exception->getMessage(),
                'audio_size' => $audio?->getSize(),
                'audio_mime' => $audio?->getMimeType(),
                'audio_extension' => $audio?->getClientOriginalExtension(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Voice transcription is temporarily unavailable. Please try again.',
            ], 503);
        }
    }
}
