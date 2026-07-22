<?php

namespace Tests\Unit;

use App\Services\Admin\AiFilterService;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class AiFilterServiceTest extends TestCase
{
    public function test_it_uses_the_openai_facade_and_returns_json_filters(): void
    {
        $response = CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"title":"driver","location":"Karachi"}',
                    ],
                ],
            ],
        ]);
        $fake = OpenAI::fake([$response]);

        $filters = (new AiFilterService())->generateFilters([
            'query' => 'driver in Karachii',
        ], 'job');

        $this->assertSame('driver', $filters['title']);
        $this->assertSame('Karachi', $filters['location']);
        $fake->chat()->assertSent(function ($method, $parameters) {
            return $method === 'create'
                && $parameters['temperature'] === 0
                && $parameters['response_format'] === ['type' => 'json_object'];
        });
    }

    public function test_staff_prompt_does_not_infer_nearby_from_generic_telugu(): void
    {
        $response = CreateResponse::fake([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"role":"driver"}',
                    ],
                ],
            ],
        ]);
        $fake = OpenAI::fake([$response]);

        $filters = (new AiFilterService())->generateFilters([
            'query' => 'నాకు డ్రైవర్ కావాలి',
        ]);

        $this->assertSame(['role' => 'driver'], $filters);
        $fake->chat()->assertSent(function ($method, $parameters) {
            $systemPrompt = $parameters['messages'][0]['content'] ?? '';

            return $method === 'create'
                && str_contains($systemPrompt, 'A generic request for staff is NOT nearby')
                && str_contains($systemPrompt, 'నాకు డ్రైవర్ కావాలి');
        });
    }
}
