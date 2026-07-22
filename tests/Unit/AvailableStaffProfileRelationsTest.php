<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class AvailableStaffProfileRelationsTest extends TestCase
{
    private function relations(): array
    {
        $method = new ReflectionMethod(
            UserController::class,
            'availableStaffProfileRelations'
        );

        return $method->invoke(app(UserController::class));
    }

    public function test_profile_does_not_load_reviews_when_table_is_missing(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('reviews')
            ->andReturnFalse();

        $this->assertSame(
            ['addresses', 'userWorkInfo', 'addedByUser', 'lastExp'],
            $this->relations()
        );
    }

    public function test_profile_loads_reviews_when_table_exists(): void
    {
        Schema::shouldReceive('hasTable')
            ->once()
            ->with('reviews')
            ->andReturnTrue();

        $this->assertContains('reviewsReceived', $this->relations());
    }
}
