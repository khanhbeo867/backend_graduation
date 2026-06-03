<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Enums\ItemCategoryType;
use App\Http\Controllers\Api\Concerns\HandlesCatalogItems;
use App\Http\Controllers\Controller;
use App\Models\EquipmentProp;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InventoryController extends Controller
{
    use HandlesCatalogItems;

    private const INVENTORY_ITEM_RELATIONS = [
        'item.itemCategory',
        'item.galleryImages.category',
        'item.galleryImages.creator.employee',
        'item.inventoryItems.inventoryCondition',
        'inventoryCondition',
        'warehouse',
    ];

    public function costumes(Request $request): JsonResponse
    {
        return $this->rawSuccess($this->costumeInventoryList($request));
    }

    public function props(Request $request): JsonResponse
    {
        if ($this->usesColonFilter($request)) {
            return $this->rawSuccess($this->inventoryList($request, ItemCategoryType::EQUIPMENT_PROPS));
        }

        return $this->rawSuccess($this->groupedInventoryList($request, ItemCategoryType::EQUIPMENT_PROPS));
    }

    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer', Rule::exists('equipment_props', 'id')],
            'item_type' => ['required', Rule::enum(ItemCategoryType::class)],
            'inventory_condition_id' => ['nullable', 'integer', Rule::exists('inventory_conditions', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')],
            'size' => ['nullable', 'string', Rule::in(['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $type = ItemCategoryType::from($data['item_type']);
        $this->ensureItemType((int) $data['item_id'], $type);
        $this->ensureImportSize((int) $data['item_id'], $type, $data['size'] ?? null);
        $this->ensureWarehouseType((int) $data['warehouse_id'], $type);
        $data['inventory_condition_id'] ??= $this->defaultInventoryConditionId();

        $items = DB::transaction(function () use ($data): array {
            $created = [];

            for ($i = 0; $i < (int) $data['quantity']; $i++) {
                $created[] = InventoryItem::query()->create([
                    'sku' => $this->nextSku($data),
                    'item_id' => $data['item_id'],
                    'item_type' => $data['item_type'],
                    'inventory_condition_id' => $data['inventory_condition_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'status' => InventoryItemStatus::AVAILABLE->value,
                    'size' => $data['size'] ?? null,
                    'is_active' => true,
                ])->load(self::INVENTORY_ITEM_RELATIONS);
            }

            return array_map(fn(InventoryItem $item) => $this->transformInventoryItem($item), $created);
        });

        return $this->success($items, 'Nhập kho thành công!', Response::HTTP_CREATED);
    }

    public function updateStatus(Request $request, string $sku): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(InventoryItemStatus::class)],
        ]);

        $item = InventoryItem::query()->where('sku', $sku)->firstOrFail();
        $item->update(['status' => $data['status']]);

        return $this->rawSuccess($this->transformInventoryItem($item->fresh(self::INVENTORY_ITEM_RELATIONS)));
    }

    public function destroyBySku(Request $request, string $sku): JsonResponse
    {
        $item = InventoryItem::query()->where('sku', $sku)->firstOrFail();

        if ($this->shouldDeletePermanently($request)) {
            $item->delete();
        } else {
            $item->update(['is_active' => false]);
        }

        return $this->rawSuccess(['message' => 'Inventory report deleted successfully']);
    }

    public function conditions(Request $request): JsonResponse
    {
        $query = InventoryCondition::query();

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        return $this->rawSuccess($query->orderBy('code')->get()->toArray());
    }

    private function inventoryList(Request $request, ItemCategoryType $type): array
    {
        $query = InventoryItem::query()
            ->where('item_type', $type->value)
            ->with(self::INVENTORY_ITEM_RELATIONS);

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        foreach (['item_id', 'inventory_condition_id', 'warehouse_id', 'status', 'size', 'sku'] as $field) {
            $inValue = $request->query($field . ':in');

            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($field, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $eqValue = $request->query($field . ':eq', $request->query($field));

            if ($eqValue !== null && $eqValue !== '') {
                $query->where($field, $eqValue);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('sku', 'like', "%{$keyword}%")
                    ->orWhereHas('item', fn(Builder $itemQuery) => $itemQuery->where('name', 'like', "%{$keyword}%"));
            });
        }

        return $query->orderByDesc('id')
            ->get()
            ->map(fn(InventoryItem $item) => $this->transformInventoryItem($item))
            ->all();
    }

    private function usesColonFilter(Request $request): bool
    {
        foreach (array_keys($request->query()) as $key) {
            if (str_contains((string) $key, ':')) {
                return true;
            }
        }

        return false;
    }

    private function groupedInventoryList(Request $request, ItemCategoryType $type): array
    {
        $query = InventoryItem::query()
            ->where('item_type', $type->value)
            ->with(self::INVENTORY_ITEM_RELATIONS);

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        foreach (['item_id', 'inventory_condition_id', 'warehouse_id', 'status', 'size', 'sku'] as $field) {
            $inValue = $request->query($field . ':in');

            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($field, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $eqValue = $request->query($field . ':eq', $request->query($field));

            if ($eqValue !== null && $eqValue !== '') {
                $query->where($field, $eqValue);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('sku', 'like', "%{$keyword}%")
                    ->orWhereHas('item', fn(Builder $itemQuery) => $itemQuery->where('name', 'like', "%{$keyword}%"));
            });
        }

        return $query->orderByDesc('item_id')
            ->orderBy('sku')
            ->get()
            ->groupBy(fn (InventoryItem $inventoryItem) => $inventoryItem->item_id.'-'.$inventoryItem->item_type?->value)
            ->map(fn ($items) => $this->transformInventoryGroup($items->values()))
            ->values()
            ->all();
    }

    private function costumeInventoryList(Request $request): array
    {
        $query = InventoryItem::query()
            ->where('item_type', ItemCategoryType::COSTUME->value)
            ->with(self::INVENTORY_ITEM_RELATIONS);

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        foreach (['item_id', 'inventory_condition_id', 'warehouse_id', 'status', 'size', 'sku'] as $field) {
            $inValue = $request->query($field . ':in');

            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($field, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $eqValue = $request->query($field . ':eq', $request->query($field));

            if ($eqValue !== null && $eqValue !== '') {
                $query->where($field, $eqValue);
            }
        }

        $keyword = trim((string) $request->query('keyword', ''));

        if ($keyword !== '') {
            $query->where(function (Builder $builder) use ($keyword): void {
                $builder
                    ->where('sku', 'like', "%{$keyword}%")
                    ->orWhereHas('item', fn(Builder $itemQuery) => $itemQuery->where('name', 'like', "%{$keyword}%"));
            });
        }

        return $query->orderByDesc('item_id')
            ->orderBy('inventory_condition_id')
            ->orderBy('size')
            ->orderBy('sku')
            ->get()
            ->groupBy(fn (InventoryItem $inventoryItem) => $inventoryItem->item_id.'-'.$inventoryItem->item_type?->value)
            ->map(fn ($items) => $this->transformCostumeInventoryGroup($items->values()))
            ->values()
            ->all();
    }

    private function transformCostumeInventoryGroup($items): array
    {
        /** @var InventoryItem $firstItem */
        $firstItem = $items->first();
        $costume = $firstItem->item;
        $condition = $firstItem->inventoryCondition;
        $originalPrice = $costume?->rental_price_per_day !== null ? (float) $costume->rental_price_per_day : null;
        $discountRate = $condition?->discount_rate !== null ? (float) $condition->discount_rate : 0.0;

        return [
            'id' => $costume?->id,
            'item_id' => $costume?->id,
            'slug' => $costume?->slug,
            'name' => $costume?->name,
            'item_type' => $firstItem->item_type?->value,
            'category' => $costume?->itemCategory ? [
                'name' => $costume->itemCategory->name,
                'slug' => $costume->itemCategory->slug,
                'type' => $costume->itemCategory->type?->value,
                'is_active' => $costume->itemCategory->is_active,
                'created_at' => $costume->itemCategory->created_at?->toISOString(),
                'updated_at' => $costume->itemCategory->updated_at?->toISOString(),
                'id' => $costume->itemCategory->id,
            ] : null,
            'color' => $costume?->color,
            'sizes' => $costume?->sizes ?? [],
            'unit' => $costume?->unit,
            'gender' => $costume?->gender?->value,
            'price' => $costume?->price !== null ? (float) $costume->price : 0,
            'rental_price_per_day' => $originalPrice,
            'in_stock' => $items->contains(
                fn (InventoryItem $inventoryItem): bool => $inventoryItem->status?->value === InventoryItemStatus::AVAILABLE->value
            ),
            'original_rental_price_per_day' => $originalPrice,
            'current_rental_price_per_day' => $originalPrice !== null
                ? round($originalPrice * (1 - $discountRate), 2)
                : null,
            'images' => $costume?->galleryImages
                ->where('is_active', true)
                ->values()
                ->map(fn ($image) => [
                    'id' => $image->id,
                    'file_name' => $image->file_name,
                    'size' => $image->size,
                    'dest' => $this->galleryImageRelativePath($image),
                    'mime_type' => $image->mime_type,
                ])
                ->all() ?? [],
            'inventory_condition' => $condition ? [
                'id' => $condition->id,
                'created_at' => $condition->created_at?->toISOString(),
                'code' => $condition->code,
                'label' => $condition->label,
                'badge_color' => $condition->badge_color,
                'discount_rate' => $condition->discount_rate !== null ? (float) $condition->discount_rate : null,
                'rentable' => $condition->rentable,
                'disposable' => $condition->disposable,
                'is_active' => $condition->is_active,
            ] : null,
            'details' => $items
                ->map(fn (InventoryItem $inventoryItem) => [
                    'id' => $inventoryItem->id,
                    'sku' => $inventoryItem->sku,
                    'size' => $inventoryItem->size,
                    'status' => $inventoryItem->status?->value,
                    'warehouse_id' => $inventoryItem->warehouse_id,
                    'warehouse' => $inventoryItem->warehouse ? [
                        'id' => $inventoryItem->warehouse->id,
                        'name' => $inventoryItem->warehouse->name,
                    ] : null,
                    'created_at' => $inventoryItem->created_at?->toISOString(),
                    'updated_at' => $inventoryItem->updated_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    private function transformInventoryGroup($items): array
    {
        /** @var InventoryItem $firstItem */
        $firstItem = $items->first();
        $item = $firstItem->item;
        $condition = $firstItem->inventoryCondition;

        return [
            'id' => $item?->id,
            'item_id' => $item?->id,
            'slug' => $item?->slug,
            'name' => $item?->name,
            'item_type' => $firstItem->item_type?->value,
            'price' => $item?->price !== null ? (float) $item->price : 0,
            'category' => $item?->itemCategory ? [
                'id' => $item->itemCategory->id,
                'name' => $item->itemCategory->name,
                'slug' => $item->itemCategory->slug,
                'type' => $item->itemCategory->type?->value,
                'is_active' => $item->itemCategory->is_active,
                'created_at' => $item->itemCategory->created_at?->toISOString(),
                'updated_at' => $item->itemCategory->updated_at?->toISOString(),
            ] : null,
            'unit' => $item?->unit,
            'images' => $item?->galleryImages
                ->where('is_active', true)
                ->values()
                ->map(fn ($image) => [
                    'id' => $image->id,
                    'file_name' => $image->file_name,
                    'size' => $image->size,
                    'dest' => $this->galleryImageRelativePath($image),
                    'url' => $this->absolutePublicUrl($this->galleryImagePublicPath($image)),
                    'mime_type' => $image->mime_type,
                ])
                ->all() ?? [],
            'rental_price_per_day' => $item?->rental_price_per_day !== null ? (float) $item->rental_price_per_day : 0,
            'inventory_condition' => $condition ? [
                'id' => $condition->id,
                'created_at' => $condition->created_at?->toISOString(),
                'code' => $condition->code,
                'label' => $condition->label,
                'badge_color' => $condition->badge_color,
                'discount_rate' => $condition->discount_rate !== null ? (float) $condition->discount_rate : null,
                'rentable' => $condition->rentable,
                'disposable' => $condition->disposable,
                'is_active' => $condition->is_active,
            ] : null,
            'in_stock' => $items->contains(
                fn (InventoryItem $inventoryItem): bool => $inventoryItem->status?->value === InventoryItemStatus::AVAILABLE->value
            ),
            'item' => $item ? $this->transformCatalogItem($item) : null,
            'details' => $items
                ->sortBy('sku')
                ->values()
                ->map(fn (InventoryItem $inventoryItem) => [
                    'id' => $inventoryItem->id,
                    'sku' => $inventoryItem->sku,
                    'status' => $inventoryItem->status?->value,
                    'warehouse_id' => $inventoryItem->warehouse_id,
                    'warehouse' => $inventoryItem->warehouse ? [
                        'id' => $inventoryItem->warehouse->id,
                        'name' => $inventoryItem->warehouse->name,
                    ] : null,
                    'created_at' => $inventoryItem->created_at?->toISOString(),
                    'updated_at' => $inventoryItem->updated_at?->toISOString(),
                ])
                ->all(),
        ];
    }

    private function transformInventoryItem(InventoryItem $item): array
    {
        $item->loadMissing(self::INVENTORY_ITEM_RELATIONS);

        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'item_id' => $item->item_id,
            'item_type' => $item->item_type?->value,
            'item' => $item->item ? $this->transformCatalogItem($item->item) : null,
            'inventory_condition_id' => $item->inventory_condition_id,
            'inventory_condition' => $item->inventoryCondition,
            'warehouse_id' => $item->warehouse_id,
            'warehouse' => $item->warehouse,
            'status' => $item->status?->value,
            'size' => $item->size,
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    private function shouldDeletePermanently(Request $request): bool
    {
        if ($request->has('permanantly') && in_array($request->query('permanantly'), [null, ''], true)) {
            return true;
        }

        if ($request->has('permanantly')) {
            return $request->boolean('permanantly');
        }

        if ($request->has('permanently') && in_array($request->query('permanently'), [null, ''], true)) {
            return true;
        }

        return $request->boolean('permanently');
    }

    private function ensureItemType(int $itemId, ItemCategoryType $type): void
    {
        $exists = EquipmentProp::query()
            ->whereKey($itemId)
            ->whereHas('itemCategory', fn(Builder $query) => $query->where('type', $type->value))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'item_id' => ["Vật phẩm không thuộc loại danh mục {$type->value}."],
            ]);
        }
    }

    private function ensureWarehouseType(int $warehouseId, ItemCategoryType $type): void
    {
        $warehouse = Warehouse::query()->findOrFail($warehouseId);
        $warehouseType = $warehouse->type?->value ?? $warehouse->type;

        if ($warehouseType !== $type->value) {
            throw ValidationException::withMessages([
                'warehouse_id' => ["Kho {$warehouseType} khong ho tro loai {$type->value}."],
            ]);
        }
    }

    private function ensureImportSize(int $itemId, ItemCategoryType $type, ?string $size): void
    {
        if ($type !== ItemCategoryType::COSTUME) {
            return;
        }

        if ($size === null || $size === '') {
            throw ValidationException::withMessages([
                'size' => ['size is required when item_type is COSTUME.'],
            ]);
        }

        $configuredSizes = EquipmentProp::query()->find($itemId)?->sizes ?? [];
        $availableSizes = collect($configuredSizes)
            ->map(fn ($value): string => strtoupper((string) $value))
            ->all();

        if (! in_array(strtoupper($size), $availableSizes, true)) {
            throw ValidationException::withMessages([
                'size' => ["size {$size} is not valid for this costume."],
            ]);
        }
    }

    private function defaultInventoryConditionId(): int
    {
        $conditionId = InventoryCondition::query()
            ->where('is_active', true)
            ->orderByRaw("case when code = 'A' then 0 else 1 end")
            ->orderBy('id')
            ->value('id');

        if (! $conditionId) {
            throw ValidationException::withMessages([
                'inventory_condition_id' => ['Chua co tinh trang ton kho mac dinh.'],
            ]);
        }

        return (int) $conditionId;
    }

    private function nextSku(array $data): string
    {
        $prefix = ($data['item_type'] === ItemCategoryType::COSTUME->value ? 'TP' : 'DC')
            . '-' . $data['item_id']
            . (! empty($data['size']) ? '-' . $data['size'] : '');
        $count = InventoryItem::query()
            ->where('sku', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->count() + 1;

        return $prefix . '-' . str_pad((string) $count, 4, '0', STR_PAD_LEFT);
    }

}
