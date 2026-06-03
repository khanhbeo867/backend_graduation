<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Http\Controllers\Api\Concerns\FormatsFrontendWorkflow;
use App\Http\Controllers\Controller;
use App\Models\InventoryCondition;
use App\Models\InventoryItem;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use App\Models\ReturnForm;
use App\Models\ReturnFormItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReturnFormController extends Controller
{
    use FormatsFrontendWorkflow;

    public function index(Request $request): JsonResponse
    {
        $query = ReturnForm::query()
            ->with([
                'createdByEmployee',
                'items.inventory.warehouse',
                'items.item',
                'loanForm.items.inventory.warehouse',
                'penaltyForms',
                'invoices',
            ]);

        $this->applySimpleFilters($query, $request, [
            'id',
            'code',
            'loan_form_code',
            'returnee_role',
            'method',
            'status',
            'is_active',
            'created_by',
        ]);

        if ($keyword = trim((string) $request->query('keyword', ''))) {
            $query->where(function ($builder) use ($keyword): void {
                $builder
                    ->where('code', 'like', "%{$keyword}%")
                    ->orWhere('loan_form_code', 'like', "%{$keyword}%")
                    ->orWhere('returnee_name', 'like', "%{$keyword}%")
                    ->orWhere('returnee_phone', 'like', "%{$keyword}%");
            });
        }

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (ReturnForm $returnForm): array => $this->transformReturnForm($returnForm))
                ->all()
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->rawSuccess($this->transformReturnForm($this->findReturnForm($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_form_code' => ['required', 'string', 'max:255'],
            'returnee_name' => ['required', 'string', 'max:255'],
            'returnee_phone' => ['required', 'string', 'max:50'],
            'returnee_citizen_id_number' => ['nullable', 'string', 'max:50'],
            'remark' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(['RETURNED', 'INSPECTED', 'COMPLETED', 'CANCELED'])],
            'skus' => ['nullable', 'array'],
            'skus.*' => ['string', 'max:255'],
        ]);

        $returnForm = DB::transaction(function () use ($data, $request): ReturnForm {
            $loanForm = LoanForm::query()
                ->with('items')
                ->where('code', $data['loan_form_code'])
                ->first();

            if (! $loanForm) {
                throw ValidationException::withMessages([
                    'loan_form_code' => ['Loan form not found for provided loan_form_code'],
                ]);
            }

            if ($loanForm->status !== 'BORROWING') {
                throw ValidationException::withMessages([
                    'status' => ['Only BORROWING loan forms can create return form'],
                ]);
            }

            $createdBy = $this->workflowEmployeeId($request);
            $returnForm = ReturnForm::query()->create([
                'code' => $this->nextWorkflowCode(ReturnForm::class, 'RF'),
                'loan_form_code' => $loanForm->code,
                'returnee_name' => $data['returnee_name'],
                'returnee_phone' => $data['returnee_phone'],
                'returnee_citizen_id_number' => $data['returnee_citizen_id_number'] ?? null,
                'remark' => $data['remark'] ?? null,
                'returnee_role' => $loanForm->borrower_role,
                'method' => $loanForm->method,
                'created_by' => $createdBy,
                'status' => $data['status'] ?? 'INSPECTED',
                'is_active' => true,
            ]);

            $skus = collect($data['skus'] ?? [])
                ->map(fn ($sku): string => trim((string) $sku))
                ->filter()
                ->values()
                ->all();

            if ($skus === []) {
                $skus = $loanForm->items->pluck('sku')->all();
            }

            $this->createReturnItemsFromSkus($returnForm, $skus, $createdBy);

            return $returnForm;
        });

        return $this->rawSuccess($this->transformReturnForm($this->findReturnForm($returnForm->id)), Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $returnForm = ReturnForm::query()->findOrFail($id);

        $data = $request->validate([
            'loan_form_code' => ['sometimes', 'required', 'string', 'max:255'],
            'returnee_name' => ['sometimes', 'required', 'string', 'max:255'],
            'returnee_phone' => ['sometimes', 'required', 'string', 'max:50'],
            'returnee_citizen_id_number' => ['nullable', 'string', 'max:50'],
            'remark' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['RETURNED', 'INSPECTED', 'COMPLETED', 'CANCELED'])],
        ]);

        if (isset($data['loan_form_code'])) {
            $loanForm = LoanForm::query()->where('code', $data['loan_form_code'])->firstOrFail();
            $data['returnee_role'] = $loanForm->borrower_role;
            $data['method'] = $loanForm->method;
        }

        $returnForm->update([
            ...$data,
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        return $this->rawSuccess($this->transformReturnForm($this->findReturnForm($id)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $returnForm = ReturnForm::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            ReturnFormItem::query()->where('return_form_code', $returnForm->code)->delete();
            $returnForm->delete();
        } else {
            $returnForm->update([
                'is_active' => false,
                'updated_by' => $this->workflowEmployeeId($request),
            ]);
        }

        return $this->rawSuccess(['message' => 'Return form deleted successfully']);
    }

    public function addItems(Request $request, int $id): JsonResponse
    {
        $returnForm = ReturnForm::query()->findOrFail($id);

        $data = $request->validate([
            'skus' => ['required', 'array', 'min:1'],
            'skus.*' => ['required', 'string', 'max:255'],
        ]);

        $this->createReturnItemsFromSkus(
            $returnForm,
            collect($data['skus'])->map(fn ($sku): string => trim((string) $sku))->filter()->values()->all(),
            $this->workflowEmployeeId($request)
        );

        return $this->rawSuccess($this->transformReturnForm($this->findReturnForm($id)));
    }

    public function inspect(Request $request, int $id): JsonResponse
    {
        $returnForm = ReturnForm::query()->findOrFail($id);

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.sku' => ['required_with:items', 'string', 'max:255'],
            'items.*.condition_on_return' => ['nullable', 'string', 'max:50'],
        ]);

        foreach ($data['items'] ?? [] as $itemData) {
            ReturnFormItem::query()
                ->where('return_form_code', $returnForm->code)
                ->where('sku', $itemData['sku'])
                ->update([
                    'condition_on_return' => $itemData['condition_on_return'] ?? 'GOOD',
                    'updated_by' => $this->workflowEmployeeId($request),
                    'updated_at' => now(),
                ]);
        }

        $returnForm->update([
            'status' => 'INSPECTED',
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        return $this->rawSuccess($this->transformReturnForm($this->findReturnForm($id)));
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $returnForm = ReturnForm::query()->with('items')->findOrFail($id);

        if ($returnForm->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Return form has no items to complete'],
            ]);
        }

        DB::transaction(function () use ($returnForm, $request): void {
            foreach ($returnForm->items as $returnItem) {
                $condition = $this->conditionForReturn($returnItem->condition_on_return);
                $status = $condition?->code === 'A' ? InventoryItemStatus::AVAILABLE->value : InventoryItemStatus::MAINTENANCE->value;
                $inventoryUpdates = [
                    'status' => $status,
                    'updated_at' => now(),
                ];

                if ($condition) {
                    $inventoryUpdates['inventory_condition_id'] = $condition->id;
                }

                InventoryItem::query()
                    ->where('sku', $returnItem->sku)
                    ->update($inventoryUpdates);

                LoanFormItem::query()
                    ->where('loan_form_code', $returnForm->loan_form_code)
                    ->where('sku', $returnItem->sku)
                    ->update([
                        'is_returned' => true,
                        'updated_at' => now(),
                    ]);
            }

            $returnForm->update([
                'status' => 'RETURNED',
                'updated_by' => $this->workflowEmployeeId($request),
            ]);

            $loanForm = LoanForm::query()
                ->with('items')
                ->where('code', $returnForm->loan_form_code)
                ->first();

            if ($loanForm && $loanForm->items->every(fn (LoanFormItem $item): bool => (bool) $item->is_returned)) {
                $loanForm->update([
                    'status' => 'RETURNED',
                    'updated_by' => $this->workflowEmployeeId($request),
                ]);
            }
        });

        return $this->rawSuccess($this->transformReturnForm($this->findReturnForm($id)));
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $item = ReturnFormItem::query()->findOrFail($id);

        $data = $request->validate([
            'sku' => ['sometimes', 'required', 'string', 'max:255'],
            'return_item_name' => ['sometimes', 'required', 'string', 'max:255'],
            'item_name' => ['sometimes', 'required', 'string', 'max:255'],
            'rental_price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'condition_on_return' => ['sometimes', 'string', 'max:50'],
            'remark' => ['nullable', 'string'],
        ]);

        if (isset($data['item_name']) && ! isset($data['return_item_name'])) {
            $data['return_item_name'] = $data['item_name'];
        }
        unset($data['item_name']);

        $item->update([
            ...$data,
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        return $this->rawSuccess($this->transformReturnItem($item->fresh(['inventory.warehouse', 'item'])));
    }

    public function destroyItem(int $id): JsonResponse
    {
        ReturnFormItem::query()->findOrFail($id)->delete();

        return $this->rawSuccess(['message' => 'Return form item deleted successfully']);
    }

    private function findReturnForm(int $id): ReturnForm
    {
        return ReturnForm::query()
            ->with([
                'createdByEmployee',
                'items.inventory.warehouse',
                'items.item',
                'loanForm.items.inventory.warehouse',
                'penaltyForms',
                'invoices',
            ])
            ->findOrFail($id);
    }

    private function createReturnItemsFromSkus(ReturnForm $returnForm, array $skus, ?int $createdBy): void
    {
        foreach (array_unique($skus) as $sku) {
            $inventory = InventoryItem::query()
                ->with('item')
                ->where('sku', $sku)
                ->first();

            if (! $inventory) {
                continue;
            }

            $exists = ReturnFormItem::query()
                ->where('return_form_code', $returnForm->code)
                ->where('sku', $sku)
                ->exists();

            if ($exists) {
                continue;
            }

            $catalogItem = $inventory->item;

            ReturnFormItem::query()->create([
                'return_form_code' => $returnForm->code,
                'sku' => $sku,
                'return_item_name' => $catalogItem?->name ?? $sku,
                'rental_price_per_day' => $catalogItem?->rental_price_per_day ?? 0,
                'condition_on_return' => 'GOOD',
                'inventory_id' => $inventory->id,
                'item_id' => $inventory->item_id,
                'item_type' => $inventory->item_type?->value ?? $inventory->item_type,
                'warehouse_id' => $inventory->warehouse_id,
                'size' => $inventory->size,
                'created_by' => $createdBy,
                'is_active' => true,
            ]);
        }
    }

    private function conditionForReturn(?string $value): ?InventoryCondition
    {
        $normalized = strtoupper(trim((string) $value));
        $code = in_array($normalized, ['A', 'GOOD', 'TOT'], true) ? 'A' : 'B';

        return InventoryCondition::query()->where('code', $code)->first()
            ?: InventoryCondition::query()->orderBy('id')->first();
    }
}
