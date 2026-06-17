<?php

namespace App\Http\Controllers\Api;

use App\Enums\InventoryItemStatus;
use App\Http\Controllers\Api\Concerns\FormatsFrontendWorkflow;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class LoanFormController extends Controller
{
    use FormatsFrontendWorkflow;

    public function index(Request $request): JsonResponse
    {
        $query = LoanForm::query()
            ->with([
                'createdByEmployee',
                'updatedByEmployee',
                'items.inventory.warehouse',
                'items.item',
                'returnForms',
                'penaltyForms',
                'invoices',
            ]);

        $this->applySimpleFilters($query, $request, [
            'id',
            'code',
            'borrower_role',
            'method',
            'status',
            'is_active',
            'created_by',
        ]);

        if ($keyword = trim((string) $request->query('keyword', ''))) {
            $query->where(function ($builder) use ($keyword): void {
                $builder
                    ->where('code', 'like', "%{$keyword}%")
                    ->orWhere('borrower_name', 'like', "%{$keyword}%")
                    ->orWhere('borrower_phone', 'like', "%{$keyword}%");
            });
        }

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (LoanForm $loanForm): array => $this->transformLoanForm($loanForm))
                ->all()
        );
    }

    public function show(int $id): JsonResponse
    {
        $loanForm = LoanForm::query()
            ->with([
                'createdByEmployee',
                'updatedByEmployee',
                'items.inventory.warehouse',
                'items.item',
                'returnForms',
                'penaltyForms',
                'invoices',
            ])
            ->findOrFail($id);

        return $this->rawSuccess($this->transformLoanForm($loanForm));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_phone' => ['required', 'string', 'max:50'],
            'borrower_citizen_id_number' => ['nullable', 'string', 'max:50'],
            'borrower_role' => ['required', 'string', 'max:50'],
            'method' => ['required', Rule::in(['BUY', 'RENT', 'RENTAL'])],
            'due_date' => ['required_unless:method,BUY', 'nullable', 'date'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
            'loan_items' => ['nullable', 'array'],
            'loan_items.*.sku' => ['nullable', 'string', 'max:255'],
        ]);

        $dueDate = null;
        if (($data['method'] ?? null) !== 'BUY' && !empty($data['due_date'])) {
            $dueDate = Carbon::parse($data['due_date'])->startOfDay();
            if ($dueDate->lt(now()->addDay()->startOfDay())) {
                throw ValidationException::withMessages([
                    'due_date' => ['due_date must be at least tomorrow'],
                ]);
            }
        }

        $loanForm = DB::transaction(function () use ($data, $dueDate, $request): LoanForm {
            $method = $data['method'] === 'RENTAL' ? 'RENT' : $data['method'];
            $createdBy = $this->workflowEmployeeId($request);
            $code = $this->nextWorkflowCode(LoanForm::class, 'LF');

            $loanForm = LoanForm::query()->create([
                'code' => $code,
                'borrower_name' => $data['borrower_name'],
                'borrower_phone' => $data['borrower_phone'],
                'borrower_citizen_id_number' => $data['borrower_citizen_id_number'] ?? null,
                'borrower_role' => $data['borrower_role'],
                'method' => $method,
                'due_date' => $dueDate,
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'remark' => $data['remark'] ?? null,
                'created_by' => $createdBy,
                'status' => in_array($method, ['RENT', 'BUY'], true) ? 'DEPOSIT_PENDING' : 'BORROWING',
                'is_active' => true,
            ]);

            $skus = collect($data['loan_items'] ?? [])
                ->map(fn (array $item): string => trim((string) ($item['sku'] ?? '')))
                ->filter()
                ->values()
                ->all();

            if ($skus !== []) {
                $this->createLoanItemsFromSkus($loanForm, $skus, $createdBy);
            }

            $this->recomputeLoanFinancials($loanForm);
            $loanForm = $loanForm->fresh();

            $requiredDeposit = (float) $loanForm->total_item_price_amount;
            if ($method === 'RENT' && $requiredDeposit > 0 && (float) $loanForm->deposit_amount >= $requiredDeposit) {
                $loanForm->update(['status' => 'BORROWING']);
            }

            if ($loanForm->fresh()->status === 'BORROWING' && $skus !== []) {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::RENTED->value);
            } elseif ($loanForm->fresh()->status === 'PAID') {
                if ($skus !== []) {
                    $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::SOLD->value);
                }
                $this->createPaidInvoiceForLoanForm($loanForm->fresh());
            }

            return $loanForm->fresh();
        });

        return $this->rawSuccess(
            $this->transformLoanForm($loanForm->load([
                'createdByEmployee',
                'updatedByEmployee',
                'items.inventory.warehouse',
                'items.item',
                'returnForms',
                'penaltyForms',
                'invoices',
            ])),
            Response::HTTP_CREATED
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->findOrFail($id);

        $data = $request->validate([
            'borrower_name' => ['sometimes', 'required', 'string', 'max:255'],
            'borrower_phone' => ['sometimes', 'required', 'string', 'max:50'],
            'borrower_citizen_id_number' => ['nullable', 'string', 'max:50'],
            'borrower_role' => ['sometimes', 'required', 'string', 'max:50'],
            'method' => ['sometimes', 'required', Rule::in(['BUY', 'RENT', 'RENTAL'])],
            'due_date' => ['sometimes', 'required_unless:method,BUY', 'nullable', 'date'],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'remark' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['DEPOSIT_PENDING', 'APPROVED', 'SHIPPING', 'DELIVERED', 'BORROWING', 'RETURNED', 'CANCELED', 'PAID'])],
        ]);

        if (isset($data['due_date']) && ($data['method'] ?? $loanForm->method) !== 'BUY') {
            $dueDate = Carbon::parse($data['due_date'])->startOfDay();
            if ($dueDate->lt(now()->addDay()->startOfDay())) {
                throw ValidationException::withMessages([
                    'due_date' => ['due_date must be at least tomorrow'],
                ]);
            }
            $data['due_date'] = $dueDate;
        } elseif (isset($data['due_date']) && ($data['method'] ?? $loanForm->method) === 'BUY') {
            $data['due_date'] = null;
        }

        if (($data['method'] ?? null) === 'RENTAL') {
            $data['method'] = 'RENT';
        }

        $data['updated_by'] = $this->workflowEmployeeId($request);
        $oldStatus = $loanForm->status;
        $loanForm->update($data);
        
        if (isset($data['status']) && $data['status'] !== $oldStatus) {
            $loanForm->loadMissing('items');
            $skus = $loanForm->items->pluck('sku')->all();
            if ($data['status'] === 'PAID') {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::SOLD->value);
                $this->createPaidInvoiceForLoanForm($loanForm->fresh());
            } elseif ($data['status'] === 'DELIVERED') {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::SOLD->value);
                $this->createIssuedInvoiceForLoanForm($loanForm->fresh());
            } elseif ($data['status'] === 'BORROWING') {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::RENTED->value);
            } elseif (in_array($data['status'], ['CANCELED', 'DEPOSIT_PENDING'], true)) {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::AVAILABLE->value);
            }
        }

        $this->recomputeLoanFinancials($loanForm->fresh());

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            LoanFormItem::query()->where('loan_form_code', $loanForm->code)->delete();
            $loanForm->delete();
        } else {
            $loanForm->update([
                'is_active' => false,
                'updated_by' => $this->workflowEmployeeId($request),
            ]);
        }

        return $this->rawSuccess(['message' => 'Loan form deleted successfully']);
    }

    public function addItems(Request $request, int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->findOrFail($id);

        $data = $request->validate([
            'skus' => ['required', 'array', 'min:1'],
            'skus.*' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($loanForm, $data, $request): void {
            $skus = collect($data['skus'])->map(fn ($sku): string => trim((string) $sku))->filter()->values()->all();
            $this->createLoanItemsFromSkus($loanForm, $skus, $this->workflowEmployeeId($request));

            if ($loanForm->status === 'BORROWING') {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::RENTED->value);
            } elseif ($loanForm->status === 'PAID') {
                $this->updateInventoryStatusBySkus($skus, InventoryItemStatus::SOLD->value);
            }

            $this->recomputeLoanFinancials($loanForm->fresh());
            if ($loanForm->fresh()->status === 'PAID') {
                $this->createPaidInvoiceForLoanForm($loanForm->fresh());
            }
        });

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function confirmDeposit(int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->with('items')->findOrFail($id);

        if (!in_array($loanForm->method, ['RENT', 'BUY'], true)) {
            throw ValidationException::withMessages([
                'method' => ['Only rental or buy loan forms can be confirmed'],
            ]);
        }

        DB::transaction(function () use ($loanForm): void {
            if ($loanForm->method === 'RENT') {
                $requiredDeposit = (float) $loanForm->total_item_price_amount;
                $loanForm->update([
                    'deposit_amount' => $requiredDeposit,
                    'status' => 'BORROWING',
                ]);

                $this->updateInventoryStatusBySkus($loanForm->items->pluck('sku')->all(), InventoryItemStatus::RENTED->value);
            } else {
                $loanForm->update([
                    'status' => 'APPROVED',
                ]);
            }
        });

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function startShipping(int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->findOrFail($id);

        if ($loanForm->status !== 'APPROVED') {
            throw ValidationException::withMessages([
                'status' => ['Only approved loan forms can be shipped'],
            ]);
        }

        if ($loanForm->method !== 'BUY') {
            throw ValidationException::withMessages([
                'method' => ['Only buy orders can be shipped'],
            ]);
        }

        $loanForm->update([
            'status' => 'SHIPPING',
        ]);

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function completeDelivery(int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->with('items')->findOrFail($id);

        if ($loanForm->status !== 'SHIPPING') {
            throw ValidationException::withMessages([
                'status' => ['Only shipping loan forms can complete delivery'],
            ]);
        }

        if ($loanForm->method !== 'BUY') {
            throw ValidationException::withMessages([
                'method' => ['Only buy orders can complete delivery'],
            ]);
        }

        DB::transaction(function () use ($loanForm): void {
            $loanForm->update([
                'status' => 'DELIVERED',
            ]);

            $this->updateInventoryStatusBySkus($loanForm->items->pluck('sku')->all(), InventoryItemStatus::SOLD->value);
            $this->createIssuedInvoiceForLoanForm($loanForm->fresh());
        });

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function checkout(int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->with('items')->findOrFail($id);

        if (in_array($loanForm->status, ['CANCELED', 'RETURNED'], true)) {
            throw ValidationException::withMessages([
                'status' => ["Loan form cannot be checked out from status {$loanForm->status}"],
            ]);
        }

        if ($loanForm->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['Loan form has no items to checkout'],
            ]);
        }

        $skus = $loanForm->items->pluck('sku')->all();
        $items = InventoryItem::query()->whereIn('sku', $skus)->get()->keyBy('sku');

        foreach ($skus as $sku) {
            $inventory = $items->get($sku);
            $status = $inventory?->status?->value ?? $inventory?->status;
            if (! $inventory || ! in_array($status, [InventoryItemStatus::AVAILABLE->value, InventoryItemStatus::RENTED->value], true)) {
                throw ValidationException::withMessages([
                    'sku' => ["Inventory sku {$sku} is not AVAILABLE"],
                ]);
            }
        }

        $this->updateInventoryStatusBySkus($skus, $loanForm->method === 'BUY' ? InventoryItemStatus::SOLD->value : InventoryItemStatus::RENTED->value);
        $loanForm->update(['status' => $loanForm->method === 'BUY' ? 'PAID' : 'BORROWING']);

        if ($loanForm->method === 'BUY') {
            $this->createPaidInvoiceForLoanForm($loanForm->fresh());
        }

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $loanForm = LoanForm::query()->with('items')->findOrFail($id);

        if ($loanForm->status === 'RETURNED') {
            throw ValidationException::withMessages([
                'status' => ['Returned loan forms cannot be canceled'],
            ]);
        }

        if (! $loanForm->created_at?->isSameDay(now())) {
            throw ValidationException::withMessages([
                'created_at' => ['Loan forms can only be canceled on the created date'],
            ]);
        }

        $this->updateInventoryStatusBySkus($loanForm->items->pluck('sku')->all(), InventoryItemStatus::AVAILABLE->value);
        $loanForm->update([
            'status' => 'CANCELED',
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        return $this->rawSuccess($this->transformLoanForm($this->findLoanForm($id)));
    }

    public function updateItem(Request $request, int $id): JsonResponse
    {
        $item = LoanFormItem::query()->findOrFail($id);

        $data = $request->validate([
            'sku' => ['sometimes', 'required', 'string', 'max:255'],
            'loan_item_name' => ['sometimes', 'required', 'string', 'max:255'],
            'item_name' => ['sometimes', 'required', 'string', 'max:255'],
            'rental_price_per_day' => ['sometimes', 'numeric', 'min:0'],
            'item_price' => ['sometimes', 'numeric', 'min:0'],
            'is_returned' => ['sometimes', 'boolean'],
            'remark' => ['nullable', 'string'],
        ]);

        if (isset($data['item_name']) && ! isset($data['loan_item_name'])) {
            $data['loan_item_name'] = $data['item_name'];
        }
        unset($data['item_name']);

        $item->update([
            ...$data,
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        if ($loanForm = LoanForm::query()->where('code', $item->loan_form_code)->first()) {
            $this->recomputeLoanFinancials($loanForm);
            if ($loanForm->fresh()->status === 'PAID') {
                $this->createPaidInvoiceForLoanForm($loanForm->fresh());
            }
        }

        return $this->rawSuccess($this->transformLoanItem($item->fresh(['inventory.warehouse', 'item'])));
    }

    public function destroyItem(int $id): JsonResponse
    {
        $item = LoanFormItem::query()->findOrFail($id);
        $loanForm = LoanForm::query()->where('code', $item->loan_form_code)->first();
        $item->delete();

        if ($loanForm) {
            $this->recomputeLoanFinancials($loanForm);
            if ($loanForm->fresh()->status === 'PAID') {
                $this->createPaidInvoiceForLoanForm($loanForm->fresh());
            }
        }

        return $this->rawSuccess(['message' => 'Loan form item deleted successfully']);
    }

    private function createPaidInvoiceForLoanForm(LoanForm $loanForm): void
    {
        if ($loanForm->method !== 'BUY') {
            return;
        }

        $totalAmount = (float) $loanForm->total_item_price_amount;

        $invoice = Invoice::query()
            ->where('loan_form_code', $loanForm->code)
            ->first();

        if ($invoice) {
            if ((float) $invoice->total_amount !== $totalAmount) {
                $invoice->update([
                    'total_amount' => $totalAmount,
                    'payment_amount' => $totalAmount,
                    'status' => 'PAID',
                    'paid_at' => $invoice->paid_at ?? now(),
                ]);
            }
            return;
        }

        Invoice::query()->create([
            'code' => $this->nextWorkflowCode(Invoice::class, 'INV'),
            'loan_form_code' => $loanForm->code,
            'total_amount' => $totalAmount,
            'payment_amount' => $totalAmount,
            'rental_amount' => 0,
            'penalty_amount' => 0,
            'refund_amount' => 0,
            'payment_method' => 'CASH',
            'payer_name' => $loanForm->borrower_name,
            'payer_phone' => $loanForm->borrower_phone,
            'payer_citizen_id_number' => $loanForm->borrower_citizen_id_number,
            'paid_at' => now(),
            'note' => 'Auto-generated invoice for BUY order ' . $loanForm->code,
            'created_by' => $loanForm->updated_by ?? $loanForm->created_by,
            'status' => 'PAID',
            'is_active' => true,
        ]);
    }

    private function createIssuedInvoiceForLoanForm(LoanForm $loanForm): void
    {
        if ($loanForm->method !== 'BUY') {
            return;
        }

        $totalAmount = (float) $loanForm->total_item_price_amount;

        $invoice = Invoice::query()
            ->where('loan_form_code', $loanForm->code)
            ->first();

        if ($invoice) {
            if ((float) $invoice->total_amount !== $totalAmount) {
                $invoice->update([
                    'total_amount' => $totalAmount,
                    'payment_amount' => 0,
                ]);
            }
            return;
        }

        Invoice::query()->create([
            'code' => $this->nextWorkflowCode(Invoice::class, 'INV'),
            'loan_form_code' => $loanForm->code,
            'total_amount' => $totalAmount,
            'payment_amount' => 0,
            'rental_amount' => 0,
            'penalty_amount' => 0,
            'refund_amount' => 0,
            'payment_method' => 'CASH',
            'payer_name' => $loanForm->borrower_name,
            'payer_phone' => $loanForm->borrower_phone,
            'payer_citizen_id_number' => $loanForm->borrower_citizen_id_number,
            'paid_at' => null,
            'note' => 'Invoice created on delivery for BUY order ' . $loanForm->code,
            'created_by' => $loanForm->updated_by ?? $loanForm->created_by,
            'status' => 'ISSUED',
            'is_active' => true,
        ]);
    }

    private function findLoanForm(int $id): LoanForm
    {
        return LoanForm::query()
            ->with([
                'createdByEmployee',
                'updatedByEmployee',
                'items.inventory.warehouse',
                'items.item',
                'returnForms',
                'penaltyForms',
                'invoices',
            ])
            ->findOrFail($id);
    }

    private function createLoanItemsFromSkus(LoanForm $loanForm, array $skus, ?int $createdBy): void
    {
        foreach (array_unique($skus) as $sku) {
            $inventory = InventoryItem::query()
                ->with('item')
                ->where('sku', $sku)
                ->first();

            if (! $inventory) {
                continue;
            }

            $exists = LoanFormItem::query()
                ->where('loan_form_code', $loanForm->code)
                ->where('sku', $sku)
                ->exists();

            if ($exists) {
                continue;
            }

            $catalogItem = $inventory->item;

            LoanFormItem::query()->create([
                'loan_form_code' => $loanForm->code,
                'sku' => $sku,
                'loan_item_name' => $catalogItem?->name ?? $sku,
                'rental_price_per_day' => $catalogItem?->rental_price_per_day ?? 0,
                'item_price' => $catalogItem?->price ?? 0,
                'inventory_id' => $inventory->id,
                'item_id' => $inventory->item_id,
                'item_type' => $inventory->item_type?->value ?? $inventory->item_type,
                'warehouse_id' => $inventory->warehouse_id,
                'size' => $inventory->size,
                'is_returned' => false,
                'created_by' => $createdBy,
                'is_active' => true,
            ]);
        }
    }

    private function recomputeLoanFinancials(LoanForm $loanForm): void
    {
        $loanForm->loadMissing('items');

        $rentalDays = 0;
        if ($loanForm->created_at && $loanForm->due_date) {
            $rentalDays = max(
                0,
                (int) $loanForm->created_at->copy()->startOfDay()
                    ->diffInDays($loanForm->due_date->copy()->startOfDay(), false)
            );
        }

        $totalRentalAmount = $loanForm->items->sum(
            fn (LoanFormItem $item): float => (float) $item->rental_price_per_day * $rentalDays
        );
        $totalItemPriceAmount = $loanForm->items->sum(fn (LoanFormItem $item): float => (float) $item->item_price);

        $loanForm->update([
            'rental_days' => $rentalDays,
            'total_rental_amount' => $totalRentalAmount,
            'total_item_price_amount' => $totalItemPriceAmount,
        ]);
    }

    private function updateInventoryStatusBySkus(array $skus, string $status): void
    {
        InventoryItem::query()
            ->whereIn('sku', array_unique(array_filter($skus)))
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }
}
