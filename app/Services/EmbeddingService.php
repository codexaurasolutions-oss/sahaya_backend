<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;

class EmbeddingService
{
    protected $client;
    protected string $model = 'text-embedding-3-small';

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key'));
    }

    /**
     * Build searchable text from a staff member's profile data.
     * This text gets embedded into a vector for semantic matching.
     */
    public function buildStaffText($user): string
    {
        $workInfo = $user->userWorkInfo ?? null;

        $parts = [];

        // Role
        if ($workInfo) {
            $roles = $workInfo->primary_role;
            if (is_array($roles)) {
                $parts[] = implode(' ', $roles);
            } elseif (!empty($roles)) {
                $parts[] = $roles;
            }

            // Skills
            $skills = $workInfo->skills;
            if (is_array($skills)) {
                $parts[] = implode(' ', $skills);
            }

            // Languages
            $langs = $workInfo->languages_spoken;
            if (is_array($langs)) {
                $parts[] = implode(' ', $langs);
            }

            // Experience
            if (!empty($workInfo->total_experience)) {
                $parts[] = $workInfo->total_experience . ' years experience';
            }

            // Additional info (may contain cuisine types, specializations, etc.)
            if (!empty($workInfo->additional_info)) {
                $parts[] = $workInfo->additional_info;
            }

            // Preferred location
            if (!empty($workInfo->preferred_work_location)) {
                $parts[] = 'preferred location: ' . $workInfo->preferred_work_location;
            }
        }

        // User-level data
        if (!empty($user->current_city)) {
            $parts[] = 'city: ' . $user->current_city;
        }
        if (!empty($user->current_state)) {
            $parts[] = 'state: ' . $user->current_state;
        }
        if (!empty($user->occupation)) {
            $parts[] = $user->occupation;
        }

        // Address
        if ($user->addresses && $user->addresses->count()) {
            $addr = $user->addresses->first();
            if (!empty($addr->city)) $parts[] = $addr->city;
            if (!empty($addr->state)) $parts[] = $addr->state;
        }

        return implode(' ', array_filter($parts));
    }

    /**
     * Generate embedding vector for a text string.
     * Returns array of floats (1536 dimensions for text-embedding-3-small).
     */
    public function generateEmbedding(string $text): ?array
    {
        if (empty(trim($text))) {
            return null;
        }

        try {
            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $text,
            ]);

            return $response->data[0]->embedding;
        } catch (\Throwable $e) {
            \Log::warning('Embedding generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);
            return null;
        }
    }

    /**
     * Generate embeddings for multiple texts in one API call (batch).
     * OpenAI supports up to 2048 inputs per call.
     * Returns array of embeddings indexed same as input.
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        // Filter out empty texts but track original indices
        $indexed = [];
        $batch = [];
        foreach ($texts as $i => $text) {
            if (!empty(trim($text ?? ''))) {
                $indexed[$i] = $text;
                $batch[] = $text;
            }
        }

        if (empty($batch)) {
            return array_fill_keys(array_keys($texts), null);
        }

        $results = array_fill_keys(array_keys($texts), null);

        try {
            // Process in chunks of 2048 (API limit)
            $chunks = array_chunk($batch, 2048);
            $offset = 0;

            foreach ($chunks as $chunk) {
                $response = $this->client->embeddings()->create([
                    'model' => $this->model,
                    'input' => $chunk,
                ]);

                // Map results back to original indices
                $indexedKeys = array_keys($indexed);
                foreach ($response->data as $j => $item) {
                    $originalIndex = $indexedKeys[$offset + $j];
                    $results[$originalIndex] = $item->embedding;
                }

                $offset += count($chunk);
            }
        } catch (\Throwable $e) {
            \Log::warning('Batch embedding generation failed', [
                'error' => $e->getMessage(),
                'count' => count($batch),
            ]);
            // Fill remaining with null
            foreach ($results as $k => $v) {
                if ($v === null && !empty(trim($texts[$k] ?? ''))) {
                    $results[$k] = null;
                }
            }
        }

        return $results;
    }

    /**
     * Compute cosine similarity between two vectors.
     * Returns float between 0 and 1 (1 = identical).
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        if ($denominator == 0.0) {
            return 0.0;
        }

        return $dotProduct / $denominator;
    }
}
