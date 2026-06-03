<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemCategoryType;
use App\Http\Controllers\Api\Concerns\HandlesCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemCategory\StoreItemCategoryRequest;
use App\Http\Requests\ItemCategory\UpdateItemCategoryRequest;
use App\Models\EquipmentProp;
use App\Models\ItemCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ItemCategoryController extends Controller
{
    use HandlesCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $embed = $this->embedList($request);
        $query = ItemCategory::query()->orderBy('name');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        $type = $request->query('type:eq', $request->query('type'));

        if ($type !== null && $type !== '') {
            $query->where('type', strtoupper((string) $type));
        }

        if ($this->shouldEmbedProducts($embed)) {
            $query->with($this->categoryProductsRelations());
        }

        $categories = $query->get()
            ->map(fn (ItemCategory $category) => $this->transformCategory($category, $embed))
            ->all();

        return $this->rawSuccess($categories);
    }

    public function store(StoreItemCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = ItemCategory::query()->create([
            ...$data,
            'slug' => $this->uniqueSlug(ItemCategory::query(), $data['slug'] ?? $data['name']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $this->success(
            $this->transformCategory($category),
            'Tao danh muc thanh cong!',
            Response::HTTP_CREATED
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $embed = $this->embedList($request);
        $category = ItemCategory::query()
            ->when(
                $this->shouldEmbedProducts($embed),
                fn ($query) => $query->with($this->categoryProductsRelations())
            )
            ->findOrFail($id);

        return $this->rawSuccess($this->transformCategory($category, $embed));
    }

    public function update(UpdateItemCategoryRequest $request, int $id): JsonResponse
    {
        $category = ItemCategory::query()->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->uniqueSlug(ItemCategory::query(), $data['slug'], $category->id);
        } elseif (array_key_exists('name', $data)) {
            $data['slug'] = $this->uniqueSlug(ItemCategory::query(), $data['name'], $category->id);
        }

        $category->update($data);

        return $this->success($this->transformCategory($category->fresh()), 'Cap nhat danh muc thanh cong!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = ItemCategory::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $category->delete();
        } else {
            $category->update(['is_active' => false]);
        }

        return $this->success(null, 'Xoa danh muc thanh cong!');
    }

    private function transformCategory(ItemCategory $category, array $embed = []): array
    {
        $data = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'type' => $category->type?->value,
            'is_active' => $category->is_active,
            'created_at' => $category->created_at?->toISOString(),
            'updated_at' => $category->updated_at?->toISOString(),
        ];

        if ($this->shouldEmbedProducts($embed)) {
            $data['products'] = $this->embeddedProducts($category);
        }

        if (in_array('costumes', $embed, true)) {
            $data['costumes'] = $this->embeddedItems($category, ItemCategoryType::COSTUME);
        }

        if (in_array('equipment_props', $embed, true)) {
            $data['equipment_props'] = $this->embeddedItems($category, ItemCategoryType::EQUIPMENT_PROPS);
        }

        return $data;
    }

    private function embeddedProducts(ItemCategory $category): array
    {
        $type = $category->type instanceof ItemCategoryType
            ? $category->type
            : ItemCategoryType::tryFrom((string) $category->type);

        if (! $type) {
            return [];
        }

        return $this->embeddedItems($category, $type);
    }

    private function embeddedItems(ItemCategory $category, ItemCategoryType $type): array
    {
        if (($category->type?->value ?? $category->type) !== $type->value) {
            return [];
        }

        $category->loadMissing($this->categoryProductsRelations());

        return $category->equipmentProps
            ->where('is_active', true)
            ->map(fn (EquipmentProp $item) => $this->transformCatalogItem($item))
            ->values()
            ->all();
    }

    private function embedList(Request $request): array
    {
        $embed = collect(explode(',', (string) $request->query('_embed', '')));
        $expand = collect(explode(',', (string) $request->query('_expand', '')))
            ->map(fn (string $item) => in_array(trim($item), ['costumes', 'equipment_props'], true) ? 'products' : $item);

        return $embed
            ->merge($expand)
            ->map(fn (string $item) => trim($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function shouldEmbedProducts(array $embed): bool
    {
        return array_intersect($embed, ['products', 'costumes', 'equipment_props']) !== [];
    }

    private function categoryProductsRelations(): array
    {
        return [
            'equipmentProps.itemCategory',
            'equipmentProps.galleryImages.category',
            'equipmentProps.galleryImages.creator.employee',
            'equipmentProps.inventoryItems.inventoryCondition',
        ];
    }

    private function shouldDeletePermanently(Request $request): bool
    {
        if (
            ($request->has('permanently') && in_array($request->query('permanently'), [null, ''], true))
            || ($request->has('permanantly') && in_array($request->query('permanantly'), [null, ''], true))
        ) {
            return true;
        }

        return $request->boolean('permanently') || $request->boolean('permanantly');
    }
}
