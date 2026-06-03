<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemCategoryType;
use App\Http\Controllers\Api\Concerns\HandlesCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentProp\StoreEquipmentPropRequest;
use App\Http\Requests\EquipmentProp\UpdateEquipmentPropRequest;
use App\Models\EquipmentProp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EquipmentPropController extends Controller
{
    use HandlesCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $query = $this->catalogItemsQuery(ItemCategoryType::EQUIPMENT_PROPS);
        $this->applyCatalogFilters($query, $request, [
            'id' => 'id',
            'category_id' => 'category_id',
        ]);

        $props = $query->orderByDesc('id')
            ->get()
            ->map(fn (EquipmentProp $item) => $this->transformCatalogItem($item))
            ->all();

        return $this->rawSuccess($props);
    }

    public function store(StoreEquipmentPropRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->ensureCategoryType($data['category_id'], ItemCategoryType::EQUIPMENT_PROPS);

        $prop = DB::transaction(function () use ($data): EquipmentProp {
            $item = EquipmentProp::query()->create(
                $this->buildCatalogPayload($data, false)
            );

            $this->syncGalleryImages($item, $data['image_ids'] ?? null, ItemCategoryType::EQUIPMENT_PROPS);

            return $item->fresh(['itemCategory', 'galleryImages']);
        });

        return $this->success(
            $this->transformCatalogItem($prop),
            'Tạo đạo cụ thành công!',
            Response::HTTP_CREATED
        );
    }

    public function show(int $id): JsonResponse
    {
        $prop = $this->catalogItemsQuery(ItemCategoryType::EQUIPMENT_PROPS)->findOrFail($id);

        return $this->rawSuccess($this->transformCatalogItem($prop));
    }

    public function update(UpdateEquipmentPropRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $prop = $this->catalogItemsQuery(ItemCategoryType::EQUIPMENT_PROPS, false)->findOrFail($id);

        if (array_key_exists('category_id', $data)) {
            $this->ensureCategoryType($data['category_id'], ItemCategoryType::EQUIPMENT_PROPS);
        }

        $prop = DB::transaction(function () use ($prop, $data): EquipmentProp {
            $payload = $this->updateCatalogPayload($data, false, $prop);

            if ($payload !== []) {
                $prop->update($payload);
            }

            if (array_key_exists('image_ids', $data)) {
                $this->syncGalleryImages($prop, $data['image_ids'], ItemCategoryType::EQUIPMENT_PROPS);
            }

            return $prop->fresh(['itemCategory', 'galleryImages']);
        });

        return $this->success($this->transformCatalogItem($prop), 'Cáº­p nháº­t Ä‘áº¡o cá»¥ thÃ nh cÃ´ng!');
    }

    public function destroy(int $id): JsonResponse
    {
        $prop = $this->catalogItemsQuery(ItemCategoryType::EQUIPMENT_PROPS, false)->findOrFail($id);
        $prop->update(['is_active' => false]);

        return $this->success(null, 'XÃ³a Ä‘áº¡o cá»¥ thÃ nh cÃ´ng!');
    }
}
