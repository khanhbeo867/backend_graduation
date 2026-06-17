<?php

namespace Tests\Feature\Api;

use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use App\Enums\UserRole;
use App\Models\EquipmentProp;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_frontend_borrow_return_billing_flow(): void
    {
        $headers = $this->authenticate();
        [$item, $inventoryItem] = $this->createInventoryFixture();

        $loanResponse = $this->withHeaders($headers)
            ->postJson('/api/loan-forms', [
                'borrower_name' => 'Nguyen Van A',
                'borrower_phone' => '0909000001',
                'borrower_citizen_id_number' => '012345678901',
                'borrower_role' => 'EXTERNAL',
                'method' => 'RENT',
                'due_date' => now()->addDays(2)->toDateString(),
                'deposit_amount' => 0,
                'loan_items' => [
                    [
                        'item_type' => ItemCategoryType::COSTUME->value,
                        'item_id' => $item->id,
                        'sku' => $inventoryItem->sku,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'DEPOSIT_PENDING')
            ->assertJsonPath('items.0.sku', $inventoryItem->sku);

        $loanId = $loanResponse->json('id');
        $loanCode = $loanResponse->json('code');

        $this->withHeaders($headers)
            ->postJson("/api/loan-forms/{$loanId}/confirm-deposit")
            ->assertOk()
            ->assertJsonPath('status', 'BORROWING')
            ->assertJsonPath('deposit_amount', 500000);

        $this->assertDatabaseHas('inventory', [
            'sku' => $inventoryItem->sku,
            'status' => InventoryItemStatus::RENTED->value,
        ]);

        $returnResponse = $this->withHeaders($headers)
            ->postJson('/api/return-forms', [
                'loan_form_code' => $loanCode,
                'returnee_name' => 'Nguyen Van A',
                'returnee_phone' => '0909000001',
                'returnee_citizen_id_number' => '012345678901',
                'remark' => 'Tra dung hen',
                'skus' => [$inventoryItem->sku],
                'status' => 'INSPECTED',
            ])
            ->assertCreated()
            ->assertJsonPath('loan_form_code', $loanCode)
            ->assertJsonPath('items.0.sku', $inventoryItem->sku);

        $returnId = $returnResponse->json('id');
        $returnCode = $returnResponse->json('code');

        $this->withHeaders($headers)
            ->postJson("/api/return-forms/{$returnId}/complete")
            ->assertOk()
            ->assertJsonPath('status', 'RETURNED');

        $this->assertDatabaseHas('inventory', [
            'sku' => $inventoryItem->sku,
            'status' => InventoryItemStatus::AVAILABLE->value,
        ]);

        $penaltyResponse = $this->withHeaders($headers)
            ->postJson('/api/penalty-forms', [
                'loan_form_code' => $loanCode,
                'return_form_code' => $returnCode,
                'reason' => 'Phi ve sinh',
                'amount' => 50000,
                'status' => 'ISSUED',
            ])
            ->assertCreated()
            ->assertJsonPath('amount', 50000);

        $this->withHeaders($headers)
            ->postJson('/api/invoices', [
                'loan_form_code' => $loanCode,
                'return_form_code' => $returnCode,
                'penalty_form_code' => $penaltyResponse->json('code'),
                'total_amount' => 650000,
                'payment_amount' => 650000,
                'rental_amount' => 600000,
                'penalty_amount' => 50000,
                'refund_amount' => 0,
                'payment_method' => 'CASH',
                'payer_name' => 'Nguyen Van A',
                'payer_phone' => '0909000001',
                'payer_citizen_id_number' => '012345678901',
                'paid_at' => now()->toISOString(),
                'status' => 'PAID',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'PAID')
            ->assertJsonPath('payment_amount', 650000);

        $this->withHeaders($headers)
            ->getJson('/api/statistics?range=7d&method=ALL')
            ->assertOk()
            ->assertJsonPath('filters.range', '7d')
            ->assertJsonPath('filters.method', 'ALL');
    }

    public function test_buy_order_automatic_invoice_generation_and_revenue(): void
    {
        $headers = $this->authenticate();
        [$item, $inventoryItem] = $this->createInventoryFixture();

        // 1. Create a BUY loan form
        $loanResponse = $this->withHeaders($headers)
            ->postJson('/api/loan-forms', [
                'borrower_name' => 'Nguyen Van B',
                'borrower_phone' => '0909000002',
                'borrower_citizen_id_number' => '987654321012',
                'borrower_role' => 'EXTERNAL',
                'method' => 'BUY',
                'deposit_amount' => 0,
                'loan_items' => [
                    [
                        'item_type' => ItemCategoryType::COSTUME->value,
                        'item_id' => $item->id,
                        'sku' => $inventoryItem->sku,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'DEPOSIT_PENDING');

        $loanId = $loanResponse->json('id');
        $loanCode = $loanResponse->json('code');

        // Verify no invoice generated yet
        $this->assertDatabaseMissing('invoices', [
            'loan_form_code' => $loanCode,
        ]);

        // 2. Confirm deposit (marking the BUY order as PAID)
        $this->withHeaders($headers)
            ->postJson("/api/loan-forms/{$loanId}/confirm-deposit")
            ->assertOk()
            ->assertJsonPath('status', 'PAID');

        // 3. Verify that a PAID invoice has been automatically generated
        $this->assertDatabaseHas('invoices', [
            'loan_form_code' => $loanCode,
            'status' => 'PAID',
            'payment_amount' => 500000.00,
            'total_amount' => 500000.00,
        ]);

        // 4. Verify that the statistics endpoint counts the revenue from this invoice
        $this->withHeaders($headers)
            ->getJson('/api/statistics?range=7d&method=BUY')
            ->assertOk()
            ->assertJsonPath('overviews.revenue', 500000);
    }

    private function authenticate(): array
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        return [
            'Authorization' => 'Bearer '.auth('api')->login($user),
        ];
    }

    private function createInventoryFixture(): array
    {
        $category = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $item = EquipmentProp::query()->create([
            'name' => 'Ao dai do',
            'slug' => 'ao-dai-do',
            'category_id' => $category->id,
            'unit' => 'SET',
            'price' => 500000,
            'rental_price_per_day' => 300000,
            'sizes' => ['M'],
            'gender' => 'FEMALE',
            'is_active' => true,
        ]);

        $condition = InventoryCondition::query()->create([
            'code' => 'A',
            'label' => 'Tot',
            'discount_rate' => 0,
            'rentable' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'KTP001',
            'name' => 'Kho trang phuc',
            'type' => ItemCategoryType::COSTUME->value,
            'is_active' => true,
        ]);

        $inventoryItem = InventoryItem::query()->create([
            'sku' => 'TP-TEST-0001',
            'item_id' => $item->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'M',
            'is_active' => true,
        ]);

        return [$item, $inventoryItem];
    }
}
