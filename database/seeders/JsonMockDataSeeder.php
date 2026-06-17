<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\GalleryImage;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\ItemCategory;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use App\Models\PenaltyForm;
use App\Models\ReturnForm;
use App\Models\ReturnFormItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JsonMockDataSeeder extends Seeder
{
    /**
     * @var array<int, int>
     */
    private array $catalogItemIdMap = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $catalogItemIdMapByType = [];

    public function run(): void
    {
        $path = database_path('seeders/data/costume-rental-db.json');

        if (! file_exists($path)) {
            $this->command?->warn("Seed file not found: {$path}");

            return;
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        Model::unguarded(function () use ($data): void {
            DB::transaction(function () use ($data): void {
                $this->seedEmployees($data['employees'] ?? []);
                $this->seedUsers($data['users'] ?? []);
                $this->syncEmployeeUsers($data['employees'] ?? []);
                $this->seedWarehouses($data['warehouses'] ?? []);
                $this->seedCategories($data['categories'] ?? []);
                $this->seedInventoryConditions($data['inventory_conditions'] ?? []);
                $this->seedImages($data['images'] ?? []);
                $this->seedCatalogItems($data['costumes'] ?? [], true);
                $this->seedCatalogItems($data['equipment_props'] ?? [], false);
                $this->seedInventory($data['inventory'] ?? []);
                $this->seedFrontendWorkflow($data);
            });
        });
    }

    private function seedEmployees(array $employees): void
    {
        foreach ($employees as $employee) {
            Employee::query()->updateOrCreate(
                ['id' => $employee['id']],
                [
                    'is_active' => $employee['is_active'] ?? true,
                    'user_id' => null,
                    'employee_code' => $employee['employee_code'],
                    'full_name' => $employee['full_name'],
                    'email' => $employee['email'],
                    'phone' => $employee['phone'] ?? null,
                    'address' => $employee['address'] ?? null,
                    'citizen_id_number' => $employee['citizen_id_number'],
                    'position' => $employee['position'],
                    'hire_date' => $employee['hire_date'] ?? null,
                    'work_status' => $employee['work_status'] ?? 'ACTIVE',
                    'remarks' => $employee['remark'] ?? $employee['remarks'] ?? null,
                    'created_at' => $employee['created_at'] ?? now(),
                    'updated_at' => $employee['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedUsers(array $users): void
    {
        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['id' => $user['id']],
                [
                    'username' => $user['username'],
                    'password' => $this->passwordValue($user['password'] ?? 'password'),
                    'role' => $user['role'] ?? 'USER',
                    'employee_id' => $user['employee_id'] ?? null,
                    'is_active' => $user['is_active'] ?? true,
                    'created_at' => $user['created_at'] ?? now(),
                    'updated_at' => $user['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function syncEmployeeUsers(array $employees): void
    {
        foreach ($employees as $employee) {
            if (empty($employee['user_id'])) {
                continue;
            }

            Employee::query()
                ->whereKey($employee['id'])
                ->update(['user_id' => $employee['user_id']]);
        }
    }

    private function seedCategories(array $categories): void
    {
        foreach ($categories as $category) {
            ItemCategory::query()->updateOrCreate(
                ['id' => $category['id']],
                [
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'type' => $category['type'],
                    'is_active' => $category['is_active'] ?? true,
                    'created_at' => $category['created_at'] ?? now(),
                    'updated_at' => $category['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedWarehouses(array $warehouses): void
    {
        foreach ($warehouses as $warehouse) {
            Warehouse::query()->updateOrCreate(
                ['id' => $warehouse['id']],
                [
                    'is_active' => $warehouse['is_active'] ?? true,
                    'code' => $warehouse['code'] ?? $this->warehouseCode($warehouse),
                    'name' => $warehouse['name'],
                    'type' => $warehouse['type'],
                    'location' => $warehouse['location'] ?? null,
                    'manager_employee_id' => $warehouse['manager_employee_id'] ?? $warehouse['managed_by'] ?? null,
                    'remarks' => $warehouse['remark'] ?? $warehouse['remarks'] ?? null,
                    'created_at' => $warehouse['created_at'] ?? now(),
                    'updated_at' => $warehouse['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedInventoryConditions(array $conditions): void
    {
        if ($conditions === []) {
            $conditions = [
                [
                    'id' => 1,
                    'is_active' => true,
                    'code' => 'A',
                    'label' => 'Con moi cho thue',
                    'discount_rate' => 0,
                    'rentable' => true,
                    'disposable' => false,
                    'badge_color' => '#22c55e',
                ],
                [
                    'id' => 2,
                    'is_active' => true,
                    'code' => 'B',
                    'label' => 'Cu cho thanh ly',
                    'discount_rate' => 0,
                    'rentable' => false,
                    'disposable' => true,
                    'badge_color' => '#eab308',
                ],
            ];
        }

        foreach ($conditions as $condition) {
            InventoryCondition::query()->updateOrCreate(
                ['id' => $condition['id']],
                [
                    'is_active' => $condition['is_active'] ?? true,
                    'code' => $condition['code'],
                    'label' => $condition['label'],
                    'discount_rate' => $condition['discount_rate'] ?? 0,
                    'rentable' => $condition['rentable'] ?? true,
                    'disposable' => $condition['disposable'] ?? false,
                    'badge_color' => $condition['badge_color'] ?? null,
                    'created_at' => $condition['created_at'] ?? now(),
                    'updated_at' => $condition['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedImages(array $images): void
    {
        foreach ($images as $image) {
            $storedImage = $this->storeSeedImage($image);

            GalleryImage::query()->updateOrCreate(
                ['id' => $image['id']],
                [
                    'file_name' => $storedImage['file_name'] ?? $image['file_name'],
                    'mime_type' => $storedImage['mime_type'] ?? $image['mime_type'] ?? null,
                    'size' => $storedImage['size'] ?? $image['size'] ?? null,
                    'dest' => $storedImage['dest'] ?? $this->seedImagePublicPath($image),
                    'category_id' => $image['category_id'],
                    'created_by' => $image['created_by'] ?? null,
                    'is_active' => $image['is_active'] ?? true,
                    'created_at' => $image['created_at'] ?? now(),
                    'updated_at' => $image['updated_at'] ?? now(),
                ]
            );
        }
    }

    private function seedCatalogItems(array $items, bool $isCostume): void
    {
        foreach ($items as $item) {
            $catalogItem = EquipmentProp::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'sku' => $item['sku'] ?? null,
                    'name' => $item['name'],
                    'category_id' => $item['category_id'],
                    'unit' => $item['unit'] ?? ($isCostume ? 'SET' : null),
                    'price' => $item['price'] ?? 0,
                    'color' => $isCostume ? $this->normalizeColor($item['color'] ?? null) : null,
                    'sizes' => $isCostume ? ($item['sizes'] ?? []) : null,
                    'gender' => $isCostume ? ($item['gender'] ?? null) : null,
                    'rental_price_per_day' => $item['rental_price_per_day'] ?? null,
                    'weight_kg' => $isCostume ? null : ($item['weight_kg'] ?? null),
                    'dimensions' => $isCostume ? null : ($item['dimensions'] ?? null),
                    'is_fragile' => $isCostume ? false : ($item['is_fragile'] ?? false),
                    'description' => $item['description'] ?? null,
                    'hashtags' => $item['hashtags'] ?? [],
                    'is_active' => $item['is_active'] ?? true,
                    'created_at' => $item['created_at'] ?? now(),
                    'updated_at' => $item['updated_at'] ?? now(),
                ]
            );

            $imageIds = collect($item['images'] ?? [])
                ->filter(fn ($id) => GalleryImage::query()->whereKey($id)->exists())
                ->values()
                ->all();

            $catalogItem->galleryImages()->sync($imageIds);

            if (isset($item['id'])) {
                $type = $isCostume ? 'COSTUME' : 'EQUIPMENT_PROPS';
                $this->catalogItemIdMapByType[$type][(int) $item['id']] = $catalogItem->id;
                $this->catalogItemIdMap[(int) $item['id']] ??= $catalogItem->id;
            }
        }
    }

    private function seedInventory(array $items): void
    {
        foreach ($items as $item) {
            $resolvedItemId = $this->mappedCatalogIdForType((int) $item['item_id'], (string) $item['item_type'])
                ?? (int) $item['item_id'];
            $inventoryConditionId = (int) ($item['inventory_condition_id'] ?? $this->defaultInventoryConditionId());

            if (
                ! EquipmentProp::query()->whereKey($resolvedItemId)->exists()
                || ! InventoryCondition::query()->whereKey($inventoryConditionId)->exists()
                || ! Warehouse::query()->whereKey($item['warehouse_id'])->exists()
            ) {
                continue;
            }

            InventoryItem::query()->updateOrCreate(
                ['id' => $item['id']],
                [
                    'is_active' => $item['is_active'] ?? true,
                    'sku' => $item['sku'],
                    'item_id' => $resolvedItemId,
                    'item_type' => $item['item_type'],
                    'inventory_condition_id' => $inventoryConditionId,
                    'warehouse_id' => $item['warehouse_id'],
                    'status' => $item['status'] ?? 'AVAILABLE',
                    'size' => $item['size'] ?? null,
                    'created_at' => $item['created_at'] ?? now(),
                    'updated_at' => $item['updated_at'] ?? now(),
                ]
            );
        }
    }

    private ?int $dateShiftSeconds = null;

    private function shiftDate(mixed $dateStr): mixed
    {
        if ($dateStr === null || $dateStr === '') {
            return null;
        }

        if ($this->dateShiftSeconds === null) {
            $maxJsonDate = \Carbon\Carbon::parse('2026-05-26T03:09:39.000Z');
            $this->dateShiftSeconds = now()->timestamp - $maxJsonDate->timestamp;
        }

        return \Carbon\Carbon::parse($dateStr)->addSeconds($this->dateShiftSeconds);
    }

    private function seedFrontendWorkflow(array $data): void
    {
        foreach ($data['loan_forms'] ?? [] as $row) {
            LoanForm::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'borrower_name' => $row['borrower_name'],
                    'borrower_phone' => $row['borrower_phone'],
                    'borrower_citizen_id_number' => $row['borrower_citizen_id_number'] ?? null,
                    'borrower_role' => $row['borrower_role'],
                    'method' => $row['method'] === 'BORROW'
                        ? ($row['status'] === 'RETURNED' ? 'RENT' : 'BUY')
                        : $row['method'],
                    'due_date' => $this->shiftDate($row['due_date'] ?? null),
                    'rental_days' => $row['rental_days'] ?? 0,
                    'total_rental_amount' => $row['total_rental_amount'] ?? 0,
                    'total_item_price_amount' => $row['total_item_price_amount'] ?? 0,
                    'deposit_amount' => $row['deposit_amount'] ?? 0,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => ($row['method'] === 'BORROW' && $row['status'] === 'BORROWING') 
                        ? 'PAID' 
                        : ($row['status'] ?? 'DEPOSIT_PENDING'),
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $this->shiftDate($row['created_at'] ?? now()),
                    'updated_at' => $this->shiftDate($row['updated_at'] ?? now()),
                ]
            );
        }

        foreach ($data['loan_form_items'] ?? [] as $row) {
            LoanFormItem::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'],
                    'sku' => $row['sku'],
                    'loan_item_name' => $row['loan_item_name'],
                    'rental_price_per_day' => $row['rental_price_per_day'] ?? 0,
                    'item_price' => $row['item_price'] ?? 0,
                    'inventory_id' => $this->existingInventoryId($row['inventory_id'] ?? null),
                    'item_id' => $this->mappedCatalogIdForType((int) ($row['item_id'] ?? 0), (string) ($row['item_type'] ?? '')) ?? null,
                    'item_type' => $row['item_type'] ?? null,
                    'warehouse_id' => $this->existingWarehouseId($row['warehouse_id'] ?? null),
                    'size' => $row['size'] ?? null,
                    'is_returned' => $row['is_returned'] ?? false,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $this->shiftDate($row['created_at'] ?? now()),
                    'updated_at' => $this->shiftDate($row['updated_at'] ?? now()),
                ]
            );
        }

        foreach ($data['return_forms'] ?? [] as $row) {
            ReturnForm::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'],
                    'returnee_name' => $row['returnee_name'],
                    'returnee_phone' => $row['returnee_phone'],
                    'returnee_citizen_id_number' => $row['returnee_citizen_id_number'] ?? null,
                    'remark' => $row['remark'] ?? null,
                    'returnee_role' => $row['returnee_role'] ?? null,
                    'method' => ($row['method'] ?? null) === 'BORROW' ? 'RENT' : ($row['method'] ?? null),
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'INSPECTED',
                    'created_at' => $this->shiftDate($row['created_at'] ?? now()),
                    'updated_at' => $this->shiftDate($row['updated_at'] ?? now()),
                ]
            );
        }

        foreach ($data['return_form_items'] ?? [] as $row) {
            ReturnFormItem::query()->updateOrCreate(
                ['id' => $row['id']],
                [
                    'is_active' => $row['is_active'] ?? true,
                    'return_form_code' => $row['return_form_code'],
                    'sku' => $row['sku'],
                    'return_item_name' => $row['return_item_name'],
                    'rental_price_per_day' => $row['rental_price_per_day'] ?? 0,
                    'condition_on_return' => $row['condition_on_return'] ?? 'GOOD',
                    'inventory_id' => $this->existingInventoryId($row['inventory_id'] ?? null),
                    'item_id' => $this->mappedCatalogIdForType((int) ($row['item_id'] ?? 0), (string) ($row['item_type'] ?? '')) ?? null,
                    'item_type' => $row['item_type'] ?? null,
                    'warehouse_id' => $this->existingWarehouseId($row['warehouse_id'] ?? null),
                    'size' => $row['size'] ?? null,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $this->shiftDate($row['created_at'] ?? now()),
                    'updated_at' => $this->shiftDate($row['updated_at'] ?? now()),
                ]
            );
        }

        foreach ($data['penalty_forms'] ?? [] as $row) {
            PenaltyForm::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'] ?? null,
                    'return_form_code' => $row['return_form_code'] ?? null,
                    'reason' => $row['reason'],
                    'amount' => $row['amount'] ?? 0,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'ISSUED',
                    'remark' => $row['remark'] ?? null,
                    'created_at' => $this->shiftDate($row['created_at'] ?? now()),
                    'updated_at' => $this->shiftDate($row['updated_at'] ?? now()),
                ]
            );
        }

        foreach ($data['invoices'] ?? [] as $row) {
            Invoice::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'id' => $row['id'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'loan_form_code' => $row['loan_form_code'] ?? null,
                    'return_form_code' => $row['return_form_code'] ?? null,
                    'penalty_form_code' => $row['penalty_form_code'] ?? null,
                    'total_amount' => $row['total_amount'] ?? 0,
                    'payment_amount' => $row['payment_amount'] ?? 0,
                    'rental_amount' => $row['rental_amount'] ?? 0,
                    'penalty_amount' => $row['penalty_amount'] ?? 0,
                    'refund_amount' => $row['refund_amount'] ?? 0,
                    'payment_method' => $row['payment_method'] ?? null,
                    'payer_name' => $row['payer_name'] ?? null,
                    'payer_phone' => $row['payer_phone'] ?? null,
                    'payer_citizen_id_number' => $row['payer_citizen_id_number'] ?? null,
                    'paid_at' => $this->shiftDate($row['paid_at'] ?? null),
                    'note' => $row['note'] ?? null,
                    'created_by' => $this->existingEmployeeId($row['created_by'] ?? null),
                    'updated_by' => $this->existingEmployeeId($row['updated_by'] ?? null),
                    'status' => $row['status'] ?? 'ISSUED',
                    'created_at' => $this->shiftDate($row['created_at'] ?? now()),
                    'updated_at' => $this->shiftDate($row['updated_at'] ?? now()),
                ]
            );
        }
    }

    private function mappedCatalogId(int $jsonItemId): ?int
    {
        return $this->catalogItemIdMap[$jsonItemId] ?? null;
    }

    private function mappedCatalogIdForType(int $jsonItemId, string $itemType): ?int
    {
        return $this->catalogItemIdMapByType[$itemType][$jsonItemId] ?? null;
    }

    private function existingEmployeeId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $employeeId = (int) $id;

        return Employee::query()->whereKey($employeeId)->exists() ? $employeeId : null;
    }

    private function existingInventoryId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $inventoryId = (int) $id;

        return InventoryItem::query()->whereKey($inventoryId)->exists() ? $inventoryId : null;
    }

    private function existingWarehouseId(mixed $id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }

        $warehouseId = (int) $id;

        return Warehouse::query()->whereKey($warehouseId)->exists() ? $warehouseId : null;
    }

    private function defaultInventoryConditionId(): int
    {
        return (int) (
            InventoryCondition::query()->where('code', 'A')->value('id')
            ?? InventoryCondition::query()->orderBy('id')->value('id')
            ?? 0
        );
    }

    private function warehouseCode(array $warehouse): string
    {
        $base = strtoupper(Str::slug((string) ($warehouse['name'] ?? 'warehouse'), '-')) ?: 'WAREHOUSE';

        return $base.'-'.$warehouse['id'];
    }

    private function passwordValue(string $password): string
    {
        if (Hash::isHashed($password) || preg_match('/^\$2[aby]\$/', $password) === 1) {
            return Hash::make((string) env('SEED_USER_PASSWORD', '123123'));
        }

        return Hash::make($password);
    }

    private function normalizeColor(mixed $color): ?array
    {
        if ($color === null || $color === '') {
            return null;
        }

        if (is_array($color)) {
            return $color;
        }

        return [
            'hex' => (string) $color,
        ];
    }

    private function storeSeedImage(array $image): ?array
    {
        $sourcePath = $this->findSeedImagePath($image);

        if ($sourcePath === null) {
            return null;
        }

        $fileName = pathinfo((string) ($image['file_name'] ?? basename($sourcePath)), PATHINFO_FILENAME).'.webp';
        $path = "images-gallery/{$image['category_id']}/{$fileName}";
        $contents = $this->webpContents($sourcePath);

        if ($contents === null) {
            $this->command?->warn("Cannot convert seed image to WebP: {$sourcePath}");

            return null;
        }

        Storage::disk('public')->put($path, $contents);

        return [
            'file_name' => basename($path),
            'mime_type' => 'image/webp',
            'size' => strlen($contents),
            'dest' => '/'.$image['category_id'].'/'.basename($path),
        ];
    }

    private function seedImagePublicPath(array $image): string
    {
        $fileName = basename((string) ($image['file_name'] ?? $image['dest'] ?? ''));

        if ($fileName !== '') {
            return "/{$image['category_id']}/{$fileName}";
        }

        $dest = (string) ($image['dest'] ?? '');
        $path = parse_url($dest, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            return $path;
        }

        return $dest;
    }

    private function findSeedImagePath(array $image): ?string
    {
        $sourceDir = $this->seedImageSourceDir();

        if ($sourceDir === null) {
            return null;
        }

        $fileName = basename((string) ($image['file_name'] ?? $image['dest'] ?? ''));

        if ($fileName === '') {
            return null;
        }

        $candidate = $sourceDir.DIRECTORY_SEPARATOR.$fileName;

        if (is_file($candidate)) {
            return $candidate;
        }

        foreach (scandir($sourceDir) ?: [] as $entry) {
            if (strcasecmp($entry, $fileName) === 0) {
                return $sourceDir.DIRECTORY_SEPARATOR.$entry;
            }
        }

        $this->command?->warn("Seed image not found: {$fileName}");

        return null;
    }

    private function seedImageSourceDir(): ?string
    {
        $sourceDir = (string) env('SEED_IMAGE_SOURCE_DIR', base_path('../FE-COSTUME-RENTAL/mock/images'));

        if (! is_dir($sourceDir)) {
            return null;
        }

        return rtrim($sourceDir, DIRECTORY_SEPARATOR.'/\\');
    }

    private function webpContents(string $sourcePath): ?string
    {
        if (strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION)) === 'webp') {
            $contents = file_get_contents($sourcePath);

            return is_string($contents) && $contents !== '' ? $contents : null;
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return null;
        }

        $sourceContents = file_get_contents($sourcePath);
        $image = is_string($sourceContents) ? @imagecreatefromstring($sourceContents) : false;

        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $success = imagewebp($image, null, 85);
        $contents = ob_get_clean();

        imagedestroy($image);

        return $success && is_string($contents) && $contents !== '' ? $contents : null;
    }
}
