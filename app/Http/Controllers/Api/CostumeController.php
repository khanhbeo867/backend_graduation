<?php

namespace App\Http\Controllers\Api;

use App\Enums\ItemCategoryType;
use App\Http\Controllers\Api\Concerns\HandlesCatalogItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\Costume\StoreCostumeRequest;
use App\Http\Requests\Costume\UpdateCostumeRequest;
use App\Models\EquipmentProp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CostumeController extends Controller
{
    use HandlesCatalogItems;

    public function index(Request $request): JsonResponse
    {
        $query = $this->catalogItemsQuery(ItemCategoryType::COSTUME);
        $this->applyCatalogFilters($query, $request, [
            'id' => 'id',
            'category_id' => 'category_id',
            'gender' => 'gender',
        ]);

        $costumes = $query->orderByDesc('id')
            ->get()
            ->map(fn (EquipmentProp $item) => $this->transformCatalogItem($item))
            ->all();

        return $this->rawSuccess($costumes);
    }

    public function store(StoreCostumeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->ensureCategoryType($data['category_id'], ItemCategoryType::COSTUME);

        $costume = DB::transaction(function () use ($data): EquipmentProp {
            $item = EquipmentProp::query()->create(
                $this->buildCatalogPayload($data, true)
            );

            $this->syncGalleryImages($item, $data['image_ids'] ?? null, ItemCategoryType::COSTUME);

            return $item->fresh(['itemCategory', 'galleryImages']);
        });

        return $this->success(
            $this->transformCatalogItem($costume),
            'Tạo trang phục thành công!',
            Response::HTTP_CREATED
        );
    }

    public function show(int $id): JsonResponse
    {
        $costume = $this->catalogItemsQuery(ItemCategoryType::COSTUME)->findOrFail($id);

        return $this->rawSuccess($this->transformCatalogItem($costume));
    }

    public function update(UpdateCostumeRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();
        $costume = $this->catalogItemsQuery(ItemCategoryType::COSTUME, false)->findOrFail($id);

        if (array_key_exists('category_id', $data)) {
            $this->ensureCategoryType($data['category_id'], ItemCategoryType::COSTUME);
        }

        $costume = DB::transaction(function () use ($costume, $data): EquipmentProp {
            $payload = $this->updateCatalogPayload($data, true, $costume);

            if ($payload !== []) {
                $costume->update($payload);
            }

            if (array_key_exists('image_ids', $data)) {
                $this->syncGalleryImages($costume, $data['image_ids'], ItemCategoryType::COSTUME);
            }

            return $costume->fresh(['itemCategory', 'galleryImages']);
        });

        return $this->success($this->transformCatalogItem($costume), 'Cập nhật trang phục thành công!');
    }

    public function destroy(int $id): JsonResponse
    {
        $costume = $this->catalogItemsQuery(ItemCategoryType::COSTUME, false)->findOrFail($id);
        $costume->update(['is_active' => false]);

        return $this->success(null, 'Xóa trang phục thành công!');
    }
}
