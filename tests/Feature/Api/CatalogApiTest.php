<?php

namespace Tests\Feature\Api;

use App\Enums\ItemCategoryType;
use App\Enums\InventoryItemStatus;
use App\Enums\UserRole;
use App\Enums\WarehouseType;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\ItemCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogApiTest extends TestCase
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

    public function test_authenticated_user_can_manage_item_categories(): void
    {
        $headers = $this->authenticate();

        $createResponse = $this->withHeaders($headers)
            ->postJson('/api/categories', [
                'name' => 'Trang phuc truyen thong',
                'type' => ItemCategoryType::COSTUME->value,
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('name', 'Trang phuc truyen thong')
            ->assertJsonPath('slug', 'trang-phuc-truyen-thong')
            ->assertJsonPath('type', ItemCategoryType::COSTUME->value);

        $categoryId = $createResponse->json('id');

        $this->withHeaders($headers)
            ->patchJson("/api/categories/{$categoryId}", [
                'name' => 'Ao dai',
                'type' => ItemCategoryType::COSTUME->value,
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Ao dai')
            ->assertJsonPath('slug', 'ao-dai');

        $this->withHeaders($headers)
            ->getJson('/api/categories?type:eq=COSTUME&_embed=costumes,equipment_props')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.costumes', [])
            ->assertJsonPath('0.equipment_props', []);

        $this->withHeaders($headers)
            ->deleteJson("/api/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonMissingPath('metadata');

        $this->assertDatabaseHas('item_categories', [
            'id' => $categoryId,
            'is_active' => 0,
        ]);
    }

    public function test_authenticated_user_can_upload_update_and_soft_delete_gallery_images(): void
    {
        Storage::fake('public');
        $headers = $this->authenticate();

        $category = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $uploadResponse = $this->withHeaders($headers)
            ->post('/api/images-gallery/upload', [
                'data' => json_encode(['category_id' => $category->id]),
                'files' => [
                    UploadedFile::fake()->image('costume-1.jpg'),
                    UploadedFile::fake()->image('costume-2.jpg'),
                ],
            ]);

        $uploadResponse
            ->assertCreated()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.category_id', $category->id)
            ->assertJsonPath('data.0.mime_type', 'image/webp')
            ->assertJsonPath('data.0.dest', fn (string $dest) => str_starts_with($dest, "/{$category->id}/"))
            ->assertJsonPath('data.0.created_by.id', User::query()->where('role', UserRole::ADMIN)->value('id'));

        $imageId = $uploadResponse->json('data.0.id');
        $fileName = $uploadResponse->json('data.0.file_name');

        $this->assertStringEndsWith('.webp', $fileName);
        Storage::disk('public')->assertExists("images-gallery/{$category->id}/{$fileName}");

        $this->withHeaders($headers)
            ->getJson('/api/images-gallery?_expand=category')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.category.id', $category->id)
            ->assertJsonPath('0.created_by.id', User::query()->where('role', UserRole::ADMIN)->value('id'));

        $this->withHeaders($headers)
            ->patchJson('/api/images-gallery/'.$imageId, [
                'file_name' => 'renamed.jpg',
                'category_id' => $category->id,
            ])
            ->assertOk()
            ->assertJsonPath('id', $imageId)
            ->assertJsonPath('file_name', 'renamed.jpg');

        $this->withHeaders($headers)
            ->delete('/api/images-gallery/'.$imageId)
            ->assertOk()
            ->assertJsonMissingPath('metadata');

        $this->assertDatabaseHas('gallery_images', [
            'id' => $imageId,
            'is_active' => 0,
        ]);
    }

    public function test_authenticated_user_can_create_update_and_list_costumes_and_equipment_props(): void
    {
        $headers = $this->authenticate();

        $costumeCategory = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $propCategory = ItemCategory::query()->create([
            'name' => 'Hoa mua',
            'slug' => 'hoa-mua',
            'type' => ItemCategoryType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        $costumeImage = GalleryImage::query()->create([
            'category_id' => $costumeCategory->id,
            'file_name' => 'costume.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => '/storage/images-gallery/costume.jpg',
            'is_active' => true,
        ]);

        $propImage = GalleryImage::query()->create([
            'category_id' => $propCategory->id,
            'file_name' => 'prop.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => '/storage/images-gallery/prop.jpg',
            'is_active' => true,
        ]);

        $costumeResponse = $this->withHeaders($headers)
            ->postJson('/api/costumes', [
                'name' => 'Ao tac xanh bac ha',
                'category_id' => $costumeCategory->id,
                'color' => [
                    'hex' => '#5b958c',
                    'code' => 'GRN',
                    'intensity' => 500,
                ],
                'sizes' => ['S', 'M', 'L'],
                'unit' => 'SET',
                'gender' => 'FEMALE',
                'images' => [$costumeImage->id],
                'rental_price_per_day' => 200000,
                'description' => 'Ao tac xanh co truyen',
                'hashtags' => ['ao tac'],
            ]);

        $costumeResponse
            ->assertCreated()
            ->assertJsonPath('name', 'Ao tac xanh bac ha')
            ->assertJsonPath('category.type', ItemCategoryType::COSTUME->value)
            ->assertJsonPath('color.hex', '#5b958c')
            ->assertJsonPath('color.code', 'GRN')
            ->assertJsonPath('color.intensity', 500)
            ->assertJsonPath('image_ids.0', $costumeImage->id)
            ->assertJsonPath('images.0.id', $costumeImage->id)
            ->assertJsonPath('images.0.dest', "/{$costumeCategory->id}/costume.jpg")
            ->assertJsonPath('hashtags.0', 'ao tac');

        $costumeId = $costumeResponse->json('id');

        $this->withHeaders($headers)
            ->getJson('/api/costumes?id:in='.$costumeId)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $costumeId);

        $this->withHeaders($headers)
            ->patchJson("/api/costumes/{$costumeId}", [
                'images' => [],
            ])
            ->assertOk()
            ->assertJsonPath('images', []);

        $propResponse = $this->withHeaders($headers)
            ->postJson('/api/equipment-props', [
                'name' => 'Hoa mua cam tay',
                'category_id' => $propCategory->id,
                'unit' => 'Cap',
                'rental_price_per_day' => 50000,
                'weight_kg' => 0.5,
                'demensions' => [
                    'width_cm' => 60,
                    'height_cm' => 60,
                    'depth_cm' => 10,
                ],
                'is_fragile' => false,
                'description' => 'Dao cu bieu dien',
                'tags' => ['hoa mua'],
                'image_id' => $propImage->id,
            ]);

        $propResponse
            ->assertCreated()
            ->assertJsonPath('category.type', ItemCategoryType::EQUIPMENT_PROPS->value)
            ->assertJsonPath('dimensions.width_cm', 60)
            ->assertJsonPath('weight_kg', 0.5)
            ->assertJsonPath('image_ids.0', $propImage->id)
            ->assertJsonPath('images.0.id', $propImage->id)
            ->assertJsonPath('images.0.dest', "/{$propCategory->id}/prop.jpg");

        $propId = $propResponse->json('id');

        $propCondition = InventoryCondition::query()->create([
            'code' => 'B',
            'label' => 'Kha',
            'discount_rate' => 0.1,
            'rentable' => true,
            'disposable' => false,
            'badge_color' => '#22c55e',
            'is_active' => true,
        ]);

        $propWarehouse = Warehouse::query()->create([
            'code' => 'KDC001',
            'name' => 'Kho dao cu',
            'type' => WarehouseType::EQUIPMENT_PROPS,
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'DC-1-0001',
            'item_id' => $propId,
            'item_type' => ItemCategoryType::EQUIPMENT_PROPS,
            'inventory_condition_id' => $propCondition->id,
            'warehouse_id' => $propWarehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->patchJson("/api/equipment-props/{$propId}", [
                'dimensions' => [
                    'width_cm' => 40,
                    'height_cm' => 40,
                    'depth_cm' => 8,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('dimensions.width_cm', 40);

        $this->withHeaders($headers)
            ->getJson('/api/equipment-props?id:in='.$propId)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.conditions.0.id', $propCondition->id)
            ->assertJsonPath('0.conditions.0.code', 'B')
            ->assertJsonPath('0.conditions.0.label', 'Kha')
            ->assertJsonPath('0.conditions.0.total_quantity', 1)
            ->assertJsonPath('0.conditions.0.available_quantity', 1)
            ->assertJsonPath('0.conditions.0.statuses.AVAILABLE', 1);
    }

    public function test_costumes_include_inventory_availability_by_size(): void
    {
        $headers = $this->authenticate();

        $category = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $costume = EquipmentProp::query()->create([
            'name' => 'Ao dai do',
            'slug' => 'ao-dai-do',
            'category_id' => $category->id,
            'sizes' => ['S', 'M', 'L'],
            'unit' => 'SET',
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
            'type' => WarehouseType::COSTUME,
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'TP-1-S-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'S',
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'TP-1-M-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::RENTED,
            'size' => 'M',
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->getJson('/api/costumes?id:in='.$costume->id)
            ->assertOk()
            ->assertJsonPath('0.inventory.total_quantity', 2)
            ->assertJsonPath('0.inventory.available_quantity', 1)
            ->assertJsonPath('0.inventory.is_available', true)
            ->assertJsonPath('0.inventory.by_size.0.size', 'S')
            ->assertJsonPath('0.inventory.by_size.0.available_quantity', 1)
            ->assertJsonPath('0.inventory.by_size.0.is_available', true)
            ->assertJsonPath('0.inventory.by_size.1.size', 'M')
            ->assertJsonPath('0.inventory.by_size.1.available_quantity', 0)
            ->assertJsonPath('0.inventory.by_size.1.is_available', false)
            ->assertJsonPath('0.inventory.by_size.2.size', 'L')
            ->assertJsonPath('0.inventory.by_size.2.available_quantity', 0)
            ->assertJsonPath('0.inventory.by_size.2.is_available', false)
            ->assertJsonPath('0.conditions.0.id', $condition->id)
            ->assertJsonPath('0.conditions.0.code', 'A')
            ->assertJsonPath('0.conditions.0.label', 'Tot')
            ->assertJsonPath('0.conditions.0.total_quantity', 2)
            ->assertJsonPath('0.conditions.0.available_quantity', 1)
            ->assertJsonPath('0.conditions.0.statuses.AVAILABLE', 1)
            ->assertJsonPath('0.conditions.0.statuses.RENTED', 1);
    }

    public function test_category_detail_expands_products_by_category_type(): void
    {
        $headers = $this->authenticate();

        $category = ItemCategory::query()->create([
            'name' => 'Ao dai',
            'slug' => 'ao-dai',
            'type' => ItemCategoryType::COSTUME,
            'is_active' => true,
        ]);

        $image = GalleryImage::query()->create([
            'category_id' => $category->id,
            'file_name' => 'ao-dai.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'dest' => '/storage/images-gallery/ao-dai.jpg',
            'is_active' => true,
        ]);

        $costume = EquipmentProp::query()->create([
            'name' => 'Ao dai do',
            'slug' => 'ao-dai-do',
            'category_id' => $category->id,
            'color' => ['hex' => '#ff0000'],
            'sizes' => ['S', 'M'],
            'unit' => 'SET',
            'gender' => 'FEMALE',
            'rental_price_per_day' => 200000,
            'is_active' => true,
        ]);
        $costume->galleryImages()->sync([$image->id]);

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
            'type' => WarehouseType::COSTUME,
            'is_active' => true,
        ]);

        InventoryItem::query()->create([
            'sku' => 'TP-1-S-0001',
            'item_id' => $costume->id,
            'item_type' => ItemCategoryType::COSTUME,
            'inventory_condition_id' => $condition->id,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryItemStatus::AVAILABLE,
            'size' => 'S',
            'is_active' => true,
        ]);

        $this->withHeaders($headers)
            ->getJson("/api/categories/{$category->id}?_expand=costumes")
            ->assertOk()
            ->assertJsonMissingPath('costumes')
            ->assertJsonPath('products.0.id', $costume->id)
            ->assertJsonPath('products.0.category.type', ItemCategoryType::COSTUME->value)
            ->assertJsonPath('products.0.color.hex', '#ff0000')
            ->assertJsonPath('products.0.sizes.0', 'S')
            ->assertJsonPath('products.0.rental_price_per_day', 200000)
            ->assertJsonPath('products.0.image_ids.0', $image->id)
            ->assertJsonPath('products.0.images.0.id', $image->id)
            ->assertJsonPath('products.0.inventory.total_quantity', 1)
            ->assertJsonPath('products.0.inventory.available_quantity', 1)
            ->assertJsonPath('products.0.inventory.by_size.0.size', 'S')
            ->assertJsonPath('products.0.inventory.by_size.0.is_available', true);
    }
}
