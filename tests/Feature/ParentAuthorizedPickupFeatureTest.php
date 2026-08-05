<?php

namespace Tests\Feature;

use App\Models\AuthorizedPickup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentAuthorizedPickupFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_create_only_one_authorized_pickup(): void
    {
        $parent = User::create([
            'email' => 'parent@example.com',
            'phone_number' => '5100000001',
            'password' => 'parent-secret',
            'role' => 'parent',
            'otp_verified' => true,
            'status' => true,
            'first_name' => 'Main',
            'sur_name' => 'Parent',
        ]);

        $this->actingAs($parent, 'sanctum');

        $payload = [
            'first_name' => 'Pickup',
            'last_name' => 'Middle',
            'sur_name' => 'Person',
            'phone_number' => '5200000001',
            'address' => 'Street 1',
        ];

        $this->postJson('/api/v1/parent/authorized-pickup', $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.first_name', 'Pickup')
            ->assertJsonPath('data.sur_name', 'Person');

        $this->postJson('/api/v1/parent/authorized-pickup', [
            'first_name' => 'Another',
            'sur_name' => 'Person',
            'phone_number' => '5200000002',
            'address' => 'Street 2',
        ])->assertStatus(422);

        $this->assertDatabaseCount('authorized_pickups', 1);
    }

    public function test_parent_can_update_and_delete_their_authorized_pickup(): void
    {
        $parent = User::create([
            'email' => 'parent2@example.com',
            'phone_number' => '5300000001',
            'password' => 'parent-secret',
            'role' => 'parent',
            'otp_verified' => true,
            'status' => true,
            'first_name' => 'Second',
            'sur_name' => 'Parent',
        ]);

        $authorizedPickup = AuthorizedPickup::create([
            'parent_id' => $parent->id,
            'first_name' => 'Initial',
            'sur_name' => 'Pickup',
            'phone_number' => '5400000001',
            'address' => 'Old Address',
        ]);

        $this->actingAs($parent, 'sanctum');

        $this->patchJson("/api/v1/parent/authorized-pickup/{$authorizedPickup->id}", [
            'first_name' => 'Updated',
            'sur_name' => 'Pickup',
            'address' => 'New Address',
        ])->assertOk()
            ->assertJsonPath('data.first_name', 'Updated')
            ->assertJsonPath('data.address', 'New Address');

        $this->deleteJson("/api/v1/parent/authorized-pickup/{$authorizedPickup->id}")
            ->assertOk();

        $this->assertDatabaseCount('authorized_pickups', 0);
    }
}
