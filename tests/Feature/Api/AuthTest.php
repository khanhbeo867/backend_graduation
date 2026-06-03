<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_and_fetch_profile_with_bearer_token(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'secret123',
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ]);

        $loginResponse
            ->assertOk()
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonPath('user.role', UserRole::ADMIN->value);

        $token = $loginResponse->json('access_token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('username', 'admin')
            ->assertJsonPath('role', UserRole::ADMIN->value);
    }

    public function test_login_with_wrong_password_returns_bad_request(): void
    {
        User::factory()->create([
            'username' => 'admin',
            'password' => 'secret123',
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'wrong-password',
        ])
            ->assertStatus(Response::HTTP_BAD_REQUEST)
            ->assertJsonPath('statusCode', Response::HTTP_BAD_REQUEST)
            ->assertJsonMissingPath('access_token');
    }

    public function test_role_middleware_blocks_non_matching_roles(): void
    {
        Route::middleware(['auth:api', 'role:MANAGER'])
            ->get('/api/_test/admin-only', fn () => response()->json(['ok' => true]));

        $user = User::factory()->create([
            'role' => UserRole::USER,
            'is_active' => true,
        ]);

        $token = auth('api')->login($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/_test/admin-only')
            ->assertForbidden()
            ->assertJsonPath('message', 'Bạn không có quyền truy cập.');
    }
}
