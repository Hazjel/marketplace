<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A route id that isn't a UUID is a plain 404, never a query that 500s on Postgres. */
class RouteUuidPatternTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_uuid_ids_are_not_found(): void
    {
        foreach (['product', 'store', 'product-category'] as $resource) {
            $this->getJson("/api/{$resource}/does-not-exist-abc")->assertNotFound();
        }
    }

    public function test_store_username_route_still_accepts_usernames(): void
    {
        Store::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Toko Pola',
            'username' => 'tokopola',
            'logo' => 'default.png',
            'about' => 'Test',
            'phone' => '08123456789',
            'address_id' => '1',
            'city' => 'Jakarta',
            'address' => 'Jl. Test',
            'postal_code' => '12345',
            'is_verified' => true,
        ]);

        $this->getJson('/api/store/username/tokopola')->assertOk();
    }
}
