<?php

namespace Tests\Feature\Api;

use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\InventoryConditionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryWarehouseApiTest extends TestCase
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

    public function test_admin_can_manage_warehouses_and_inventory(): void
    {
        $headers = $this->authenticate();

        $manager = Employee::query()->create([
            'employee_code' => 'D00001',
            'full_name' => 'Kho Manager',
            'email' => 'warehouse@example.com',
            'citizen_id_number' => '123456789012',
            'position' => 'WAREHOUSE_MANAGER',
            'work_status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $warehouseResponse = $this->withHeaders($headers)
            ->postJson('/api/warehouses', [
                'name' => 'Kho dao cu',
                'type' => ItemCategoryType::EQUIPMENT_PROPS->value,
                'managed_by' => $manager->id,
            ]);

        $warehouseResponse
            ->assertCreated()
            ->assertJsonPath('type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('managed_by', $manager->id);

        $warehouseId = $warehouseResponse->json('id');

        $this->withHeaders($headers)
            ->getJson('/api/warehouses')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $warehouseId);

        $category = ItemCategory::query()->create([
            'name' => 'Hoa mua',
            'slug' => 'hoa-mua',
            'type' => ItemCategoryType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        $prop = EquipmentProp::query()->create([
            'name' => 'Hoa mua cam tay',
            'slug' => 'hoa-mua-cam-tay',
            'category_id' => $category->id,
            'unit' => 'Cap',
            'rental_price_per_day' => 50000,
            'is_active' => true,
        ]);

        $image = GalleryImage::query()->create([
            'category_id' => $category->id,
            'file_name' => 'prop.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => "/{$category->id}/prop.jpg",
            'is_active' => true,
        ]);

        $prop->galleryImages()->attach($image->id);

        $condition = InventoryCondition::query()->create([
            'code' => 'A',
            'label' => 'Tot',
            'discount_rate' => 0,
            'rentable' => true,
            'is_active' => true,
        ]);

        $importResponse = $this->withHeaders($headers)
            ->postJson('/api/inventory/import', [
                'item_id' => $prop->id,
                'item_type' => ItemCategoryType::EQUIPMENT_PROPS->value,
                'inventory_condition_id' => $condition->id,
                'warehouse_id' => $warehouseId,
                'quantity' => 2,
            ]);

        $importResponse
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.item_id', $prop->id)
            ->assertJsonPath('data.0.warehouse_id', $warehouseId);

        $sku = $importResponse->json('data.0.sku');

        $this->withHeaders($headers)
            ->getJson('/api/inventory/props?warehouse_id:eq='.$warehouseId)
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.item_type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('0.item.images.0.id', $image->id)
            ->assertJsonPath('0.item.images.0.dest', "/{$category->id}/prop.jpg");

        $this->withHeaders($headers)
            ->patchJson('/api/inventory/status/'.$sku, [
                'status' => InventoryItemStatus::MAINTENANCE->value,
            ])
            ->assertOk()
            ->assertJsonPath('status', InventoryItemStatus::MAINTENANCE->value);
    }

    public function test_admin_can_list_inventory_conditions(): void
    {
        $headers = $this->authenticate();

        $this->seed(InventoryConditionSeeder::class);

        $this->withHeaders($headers)
            ->getJson('/api/inventory/conditions')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.code', 'A')
            ->assertJsonPath('1.code', 'B');
    }

    public function test_costume_inventory_is_grouped_by_costume_and_condition(): void
    {
        $headers = $this->authenticate();

        $category = ItemCategory::query()->create([
            'name' => 'Ao Dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $costume = EquipmentProp::query()->create([
            'name' => 'Ao Dai Lua Tim',
            'slug' => 'ao-dai-lua-tim',
            'category_id' => $category->id,
            'color' => [
                'hex' => '#ddd6fe',
                'code' => 'VOL',
                'intensity' => 200,
            ],
            'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL'],
            'unit' => 'SET',
            'gender' => 'FEMALE',
            'rental_price_per_day' => 300000,
            'is_active' => true,
        ]);

        $image = GalleryImage::query()->create([
            'category_id' => $category->id,
            'file_name' => 'ao-dai-cach-tan-tim-1.webp',
            'mime_type' => 'image/webp',
            'size' => 65950,
            'dest' => "/{$category->id}/ao-dai-cach-tan-tim-1.webp",
            'is_active' => true,
        ]);

        $costume->galleryImages()->attach($image->id);

        $condition = InventoryCondition::query()->create([
            'code' => 'A',
            'label' => 'Tot',
            'discount_rate' => 0,
            'rentable' => true,
            'is_active' => true,
        ]);

        $warehouse = Warehouse::query()->create([
            'code' => 'KTP001',
            'name' => 'Kho trang phuc 1',
            'type' => ItemCategoryType::COSTUME->value,
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'TP-ADLT-VOL200-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'AVAILABLE',
            'size' => 'M',
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'TP-ADLT-VOL200-0002',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'AVAILABLE',
            'size' => 'L',
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/inventory/costumes')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $costume->id)
            ->assertJsonPath('0.slug', 'ao-dai-lua-tim')
            ->assertJsonPath('0.category.id', $category->id)
            ->assertJsonPath('0.color.code', 'VOL')
            ->assertJsonPath('0.original_rental_price_per_day', 300000)
            ->assertJsonPath('0.current_rental_price_per_day', 300000)
            ->assertJsonPath('0.images.0.dest', "/{$category->id}/ao-dai-cach-tan-tim-1.webp")
            ->assertJsonPath('0.inventory_condition.id', $condition->id)
            ->assertJsonPath('0.details.0.sku', 'TP-ADLT-VOL200-0002')
            ->assertJsonPath('0.details.0.size', 'L')
            ->assertJsonPath('0.details.0.warehouse.name', 'Kho trang phuc 1')
            ->assertJsonPath('0.details.1.sku', 'TP-ADLT-VOL200-0001')
            ->assertJsonPath('0.details.1.size', 'M');
    }
}
