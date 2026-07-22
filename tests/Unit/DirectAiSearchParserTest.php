<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\StaffController;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\UserWorkInfo;
use ReflectionMethod;
use Tests\TestCase;

class DirectAiSearchParserTest extends TestCase
{
    private function invokePrivate(string $method, ...$arguments)
    {
        $controller = new StaffController();
        $reflection = new ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($controller, ...$arguments);
    }

    private function staffAt(int $id, string $city, ?float $latitude = null, ?float $longitude = null): User
    {
        $staff = (new User())->forceFill([
            'id' => $id,
            'location' => $city,
        ]);
        $address = (new UserAddress())->forceFill([
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_primary' => true,
        ]);
        $workInfo = (new UserWorkInfo())->forceFill([
            'preferred_work_location' => $city,
        ]);

        $staff->setRelation('addresses', collect([$address]));
        $staff->setRelation('userWorkInfo', $workInfo);

        return $staff;
    }

    public function test_it_extracts_role_and_explicit_location(): void
    {
        $query = 'Find a driver in Karachi with 5 years experience';

        $this->assertSame('driver', $this->invokePrivate('detectCanonicalRole', $query));
        $this->assertSame('karachi', $this->invokePrivate('extractSearchLocation', $query));
    }

    public function test_descriptive_words_are_not_treated_as_a_location(): void
    {
        $query = 'Driver job with good salary';

        $this->assertSame('driver', $this->invokePrivate('detectCanonicalRole', $query));
        $this->assertNull($this->invokePrivate('extractSearchLocation', $query));
    }

    public function test_nearby_search_does_not_create_a_literal_location(): void
    {
        $query = 'Show driver jobs near me';

        $this->assertTrue($this->invokePrivate('isNearbySearch', $query));
        $this->assertNull($this->invokePrivate('extractSearchLocation', $query));
    }

    public function test_longer_role_alias_wins_over_generic_caretaker(): void
    {
        $this->assertSame(
            'pet caretaker',
            $this->invokePrivate('detectCanonicalRole', 'Find a pet caretaker in Delhi')
        );
    }

    public function test_it_recognizes_hindi_and_telugu_roles_without_ai(): void
    {
        $this->assertSame(
            'driver',
            $this->invokePrivate('detectCanonicalRole', 'मुझे ड्राइवर चाहिए')
        );
        $this->assertSame(
            'driver',
            $this->invokePrivate('detectCanonicalRole', 'నాకు డ్రైవర్ కావాలి')
        );
        $this->assertSame(
            'cook',
            $this->invokePrivate('detectCanonicalRole', 'నాకు వంటమనిషి కావాలి')
        );
    }

    public function test_generic_telugu_request_is_not_treated_as_nearby(): void
    {
        $this->assertFalse(
            $this->invokePrivate('isNearbySearch', 'నాకు డ్రైవర్ కావాలి')
        );
        $this->assertTrue(
            $this->invokePrivate('isNearbySearch', 'నా దగ్గర డ్రైవర్ కావాలి')
        );
    }

    public function test_multilingual_fallback_uses_canonical_ai_role_and_location(): void
    {
        $resolved = $this->invokePrivate(
            'resolveBasicSearchFilters',
            'నాకు హైదరాబాద్‌లో డ్రైవర్ కావాలి',
            ['role' => 'driver', 'location' => 'Hyderabad'],
            'role',
        );

        $this->assertSame([
            'role' => 'driver',
            'location' => 'Hyderabad',
        ], $resolved);
    }

    public function test_fallback_ignores_hallucinated_nearby_without_explicit_phrase(): void
    {
        $resolved = $this->invokePrivate(
            'resolveBasicSearchFilters',
            'నాకు డ్రైవర్ కావాలి',
            ['role' => 'driver', 'nearby' => true],
            'role',
        );

        $this->assertSame('driver', $resolved['role']);
        $this->assertNull($resolved['location']);
    }

    public function test_nearby_search_uses_city_when_staff_coordinates_are_missing(): void
    {
        $results = $this->invokePrivate(
            'applyNearbyStaffProximity',
            collect([
                $this->staffAt(1, 'Indore'),
                $this->staffAt(2, 'Delhi'),
            ]),
            ['lat' => 22.7196, 'long' => 75.8577],
            50.0,
            'Indore, Madhya Pradesh',
        );

        $this->assertSame([1], $results->pluck('id')->all());
    }

    public function test_nearby_search_does_not_return_everyone_without_a_location(): void
    {
        $results = $this->invokePrivate(
            'applyNearbyStaffProximity',
            collect([
                $this->staffAt(1, 'Indore'),
                $this->staffAt(2, 'Delhi'),
            ]),
            null,
            50.0,
            '',
        );

        $this->assertCount(0, $results);
    }

    public function test_nearby_search_prefers_real_distance_when_staff_coordinates_exist(): void
    {
        $results = $this->invokePrivate(
            'applyNearbyStaffProximity',
            collect([
                $this->staffAt(1, 'Indore', 22.72, 75.86),
                $this->staffAt(2, 'Indore', 28.61, 77.21),
            ]),
            ['lat' => 22.7196, 'long' => 75.8577],
            50.0,
            'Indore',
        );

        $this->assertSame([1], $results->pluck('id')->all());
        $this->assertNotNull($results->first()->_distance_km);
    }
}
