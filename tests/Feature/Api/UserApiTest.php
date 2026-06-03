<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticateAs(UserRole $role = UserRole::ADMIN): array
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = auth('api')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }

    public function test_user_api_requires_authentication(): void
    {
        $this->getJson('/api/users')
            ->assertUnauthorized()
            ->assertJsonPath('statusCode', 401);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(2)->create();

        $this->withHeaders($this->authenticateAs())
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissingPath('statusCode')
            ->assertJsonCount(3);
    }

    public function test_admin_can_show_a_user(): void
    {
        $targetUser = User::factory()->create([
            'username' => 'target-user',
        ]);

        $this->withHeaders($this->authenticateAs())
            ->getJson("/api/users/{$targetUser->id}")
            ->assertOk()
            ->assertJsonPath('id', $targetUser->id)
            ->assertJsonPath('username', 'target-user');
    }

    public function test_admin_can_create_a_user(): void
    {
        $payload = [
            'username' => 'new-admin',
            'password' => 'secret123',
            'role' => UserRole::MANAGER->value,
            'is_active' => true,
        ];

        $this->withHeaders($this->authenticateAs())
            ->postJson('/api/users', $payload)
            ->assertCreated()
            ->assertJsonPath('statusCode', 201)
            ->assertJsonPath('username', 'new-admin')
            ->assertJsonPath('role', UserRole::MANAGER->value);

        $this->assertDatabaseHas('users', [
            'username' => 'new-admin',
            'role' => UserRole::MANAGER->value,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_update_a_user(): void
    {
        $targetUser = User::factory()->create([
            'username' => 'old-name',
            'role' => UserRole::USER,
        ]);

        $payload = [
            'username' => 'updated-name',
            'role' => UserRole::MANAGER->value,
            'is_active' => false,
        ];

        $this->withHeaders($this->authenticateAs())
            ->patchJson("/api/users/{$targetUser->id}", $payload)
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonPath('username', 'updated-name')
            ->assertJsonPath('role', UserRole::MANAGER->value)
            ->assertJsonPath('is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'username' => 'updated-name',
            'role' => UserRole::MANAGER->value,
            'is_active' => 0,
        ]);
    }

    public function test_admin_can_soft_delete_a_user(): void
    {
        $targetUser = User::factory()->create([
            'is_active' => true,
        ]);

        $this->withHeaders($this->authenticateAs())
            ->deleteJson("/api/users/{$targetUser->id}")
            ->assertOk()
            ->assertJsonPath('statusCode', 200)
            ->assertJsonMissingPath('metadata');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => 0,
        ]);
    }

    public function test_store_user_validates_unique_username(): void
    {
        User::factory()->create([
            'username' => 'existing-user',
        ]);

        $payload = [
            'username' => 'existing-user',
            'password' => 'secret123',
            'role' => UserRole::MANAGER->value,
        ];

        $this->withHeaders($this->authenticateAs())
            ->postJson('/api/users', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('statusCode', 422)
            ->assertJsonStructure(['username']);
    }
}
