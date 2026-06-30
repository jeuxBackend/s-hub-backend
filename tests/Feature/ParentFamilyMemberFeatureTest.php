<?php

namespace Tests\Feature;

use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentFamilyMemberFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_add_only_two_family_members(): void
    {
        $parent = User::create([
            'email' => 'parent@example.com',
            'phone_number' => '1000000001',
            'password' => 'parent-secret',
            'role' => 'parent',
            'otp_verified' => true,
            'status' => true,
            'first_name' => 'Main',
            'sur_name' => 'Parent',
        ]);

        $this->actingAs($parent, 'sanctum');

        $payload = fn (int $index) => [
            'first_name' => 'Family',
            'sur_name' => 'Member' . $index,
            'email' => "family{$index}@example.com",
            'phone_number' => '200000000' . $index,
            'address' => 'Street ' . $index,
            'relation_with_parent' => 'Sibling',
            'password' => 'family-pass',
        ];

        $this->postJson('/api/v1/parent/family-members', $payload(1))
            ->assertStatus(201);

        $this->postJson('/api/v1/parent/family-members', $payload(2))
            ->assertStatus(201);

        $this->postJson('/api/v1/parent/family-members', $payload(3))
            ->assertStatus(422);

        $this->assertDatabaseCount('family_members', 2);
    }

    public function test_family_member_logs_into_the_linked_parent_account(): void
    {
        $parent = User::create([
            'email' => 'parent@example.com',
            'phone_number' => '3000000001',
            'password' => 'parent-secret',
            'role' => 'parent',
            'otp_verified' => true,
            'status' => true,
            'first_name' => 'Actual',
            'sur_name' => 'Parent',
        ]);

        $familyMember = FamilyMember::create([
            'parent_id' => $parent->id,
            'first_name' => 'Helper',
            'sur_name' => 'Person',
            'email' => 'helper@example.com',
            'phone_number' => '4000000001',
            'address' => 'Family address',
            'relation_with_parent' => 'Uncle',
            'password' => 'helper-pass',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => $familyMember->email,
            'password' => 'helper-pass',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.id', $parent->id)
            ->assertJsonPath('data.user.role', 'parent')
            ->assertJsonPath('data.logged_in_via_family_member', true)
            ->assertJsonPath('data.family_member.id', $familyMember->id);
    }
}
