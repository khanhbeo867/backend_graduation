<?php

namespace Tests\Feature\Api;

use App\Enums\Position;
use App\Enums\UserRole;
use App\Enums\Work;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function authenticate(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        $token = auth('api')->login($user);

        return [
            'Authorization' => 'Bearer '.$token,
        ];
    }

    public function test_admin_can_create_update_and_filter_employees(): void
    {
        $headers = $this->authenticate();

        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/employees', [
                'full_name' => 'Nguyen Van A',
                'citizen_id_number' => '123123123123',
                'phone' => '0336089900',
                'address' => 'Hai Phong',
                'email' => 'nguyenvana@example.com',
                'position' => Position::TECHNICAL_CREW->value,
                'remarks' => 'Created from test',
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('employee_code', 'D00001')
            ->assertJsonPath('work_status', Work::ACTIVE->value)
            ->assertJsonPath('email', 'nguyenvana@example.com');

        $employeeId = $createResponse->json('id');

        $this->withHeaders($headers)
            ->patchJson("/api/employees/{$employeeId}", [
                'phone' => '0988123456',
                'address' => 'Ha Noi',
                'work_status' => Work::ON_LEAVE->value,
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('phone', '0988123456')
            ->assertJsonPath('work_status', Work::ON_LEAVE->value);

        $this->withHeaders($headers)
            ->getJson('/api/employees?position:in=MANAGER,TECHNICAL_CREW')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $employeeId);
    }
}
