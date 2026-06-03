<?php

namespace App\Http\Controllers\Api;

use App\Enums\WarehouseType;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query()->with('manager');

        if (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        foreach (['id', 'type', 'managed_by'] as $field) {
            $column = $field === 'managed_by' ? 'manager_employee_id' : $field;
            $inValue = $request->query($field . ':in');

            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($column, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $eqValue = $request->query($field . ':eq', $request->query($field));

            if ($eqValue !== null && $eqValue !== '') {
                $query->where($column, $eqValue);
            }
        }

        $warehouses = $query->orderByDesc('id')
            ->get()
            ->map(fn(Warehouse $warehouse) => $this->transformWarehouse($warehouse))
            ->all();

        return $this->rawSuccess($warehouses);
    }

    public function show(int $id): JsonResponse
    {
        $warehouse = Warehouse::query()->with('manager')->findOrFail($id);

        return $this->rawSuccess($this->transformWarehouse($warehouse));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedWarehouse($request);

        $warehouse = Warehouse::query()->create([
            'is_active' => $data['is_active'] ?? true,
            'code' => $data['code'] ?? $this->uniqueCode($data['name']),
            'name' => $data['name'],
            'type' => $data['type'],
            'location' => $data['location'] ?? null,
            'manager_employee_id' => $data['manager_employee_id'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        return $this->success($this->transformWarehouse($warehouse->fresh('manager')), 'Tạo kho thành công!', Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::query()->findOrFail($id);
        $data = $this->validatedWarehouse($request, $warehouse->id);

        if ($data !== []) {
            $warehouse->update($data);
        }

        return $this->success($this->transformWarehouse($warehouse->fresh('manager')), 'Cập nhật kho thành công!');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $warehouse->delete();
        } else {
            $warehouse->update(['is_active' => false]);
        }

        return $this->success(null, 'Xóa kho thành công!');
    }

    private function validatedWarehouse(Request $request, ?int $ignoreId = null): array
    {
        if ($request->has('manager_employee_id') || $request->has('managed_by')) {
            $request->merge([
                'manager_employee_id' => $request->input('manager_employee_id', $request->input('managed_by')),
            ]);
        }

        $required = $ignoreId === null ? 'required' : 'sometimes';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($ignoreId)],
            'type' => [$required, Rule::enum(WarehouseType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'manager_employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'remarks' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function transformWarehouse(Warehouse $warehouse): array
    {
        $warehouse->loadMissing('manager');

        return [
            'id' => $warehouse->id,
            'is_active' => $warehouse->is_active,
            'code' => $warehouse->code,
            'name' => $warehouse->name,
            'type' => $warehouse->type?->value,
            'location' => $warehouse->location,
            'managed_by' => $warehouse->manager_employee_id,
            'manager_employee_id' => $warehouse->manager_employee_id,
            'manager' => $warehouse->manager,
            'remarks' => $warehouse->remarks,
            'created_at' => $warehouse->created_at?->toISOString(),
            'updated_at' => $warehouse->updated_at?->toISOString(),
        ];
    }

    private function uniqueCode(string $name): string
    {
        $base = strtoupper(Str::slug($name, '-')) ?: 'WAREHOUSE';
        $code = $base;
        $sequence = 2;

        while (Warehouse::query()->where('code', $code)->exists()) {
            $code = $base . '-' . $sequence;
            $sequence++;
        }

        return $code;
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
