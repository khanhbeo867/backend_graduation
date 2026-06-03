<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticateAs(UserRole $role): array
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

    public function test_admin_can_access_user_routes(): void
    {
        $targetUser = User::factory()->create();

        $this->withHeaders($this->authenticateAs(UserRole::ADMIN))
            ->getJson('/api/users')
            ->assertOk();

        $this->withHeaders($this->authenticateAs(UserRole::ADMIN))
            ->getJson("/api/users/{$targetUser->id}")
            ->assertOk();
    }

    public function test_non_admin_cannot_access_user_list(): void
    {
        $this->withHeaders($this->authenticateAs(UserRole::MANAGER))
            ->getJson('/api/users')
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs(UserRole::USER))
            ->getJson('/api/users')
            ->assertForbidden();
    }

    public function test_non_admin_cannot_mutate_users(): void
    {
        $targetUser = User::factory()->create();

        $this->withHeaders($this->authenticateAs(UserRole::MANAGER))
            ->postJson('/api/users', [
                'username' => 'blocked-user',
                'password' => 'secret123',
                'role' => UserRole::USER->value,
            ])
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs(UserRole::USER))
            ->patchJson("/api/users/{$targetUser->id}", [
                'username' => 'updated-by-user',
            ])
            ->assertForbidden();

        $this->withHeaders($this->authenticateAs(UserRole::USER))
            ->deleteJson("/api/users/{$targetUser->id}")
            ->assertForbidden();
    }
}
