<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Employee;
use App\Models\EquipmentProp;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use App\Models\PenaltyForm;
use App\Models\ReturnForm;
use App\Models\ReturnFormItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FormatsFrontendWorkflow
{
    private function workflowEmployeeId(?Request $request = null): ?int
    {
        if ($request?->filled('created_by')) {
            return (int) $request->input('created_by');
        }

        $user = auth('api')->user();

        if ($user?->employee_id) {
            return (int) $user->employee_id;
        }

        if ($user?->id) {
            $employeeId = Employee::query()->where('user_id', $user->id)->value('id');

            return $employeeId ? (int) $employeeId : null;
        }

        return null;
    }

    private function nextWorkflowCode(string $modelClass, string $prefix): string
    {
        $max = $modelClass::query()
            ->pluck('code')
            ->reduce(function (int $carry, ?string $code) use ($prefix): int {
                if (! is_string($code) || preg_match('/^'.preg_quote($prefix, '/').'(\d+)$/', $code, $matches) !== 1) {
                    return $carry;
                }

                return max($carry, (int) $matches[1]);
            }, 0);

        return $prefix.str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    private function shouldDeletePermanently(Request $request): bool
    {
        if ($request->has('permanantly')) {
            return filter_var($request->query('permanantly'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->has('permanently')) {
            return filter_var($request->query('permanently'), FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    /**
     * Supports the json-server style filters used by the frontend mock:
     * field=value, field:eq=value, field:ne=value and field:in=a,b,c.
     */
    private function applySimpleFilters(Builder $query, Request $request, array $fields): Builder
    {
        foreach ($fields as $field) {
            $inValue = $request->query($field.':in');
            if ($inValue !== null && $inValue !== '') {
                $query->whereIn($field, array_filter(array_map('trim', explode(',', (string) $inValue))));
            }

            $neValue = $request->query($field.':ne');
            if ($neValue !== null && $neValue !== '') {
                $query->where($field, '!=', $neValue);
            }

            $eqValue = $request->query($field.':eq', $request->query($field));
            if ($eqValue !== null && $eqValue !== '') {
                $query->where($field, $eqValue);
            }
        }

        return $query;
    }

    private function employeePayload(?Employee $employee): ?array
    {
        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'employee_code' => $employee->employee_code,
            'full_name' => $employee->full_name,
            'phone' => $employee->phone,
            'email' => $employee->email,
        ];
    }

    private function inventoryPayload(?InventoryItem $inventory): ?array
    {
        if (! $inventory) {
            return null;
        }

        $inventory->loadMissing(['warehouse', 'inventoryCondition']);

        return [
            'id' => $inventory->id,
            'sku' => $inventory->sku,
            'item_id' => $inventory->item_id,
            'item_type' => $inventory->item_type?->value ?? $inventory->item_type,
            'inventory_condition_id' => $inventory->inventory_condition_id,
            'inventory_condition' => $inventory->inventoryCondition,
            'warehouse_id' => $inventory->warehouse_id,
            'warehouse' => $inventory->warehouse ? [
                'id' => $inventory->warehouse->id,
                'name' => $inventory->warehouse->name,
            ] : null,
            'status' => $inventory->status?->value ?? $inventory->status,
            'size' => $inventory->size,
            'is_active' => $inventory->is_active,
            'created_at' => $inventory->created_at?->toISOString(),
            'updated_at' => $inventory->updated_at?->toISOString(),
        ];
    }

    private function catalogItemPayload(?EquipmentProp $item): ?array
    {
        if (! $item) {
            return null;
        }

        $item->loadMissing(['itemCategory', 'galleryImages']);

        return [
            'id' => $item->id,
            'sku' => $item->sku,
            'slug' => $item->slug,
            'name' => $item->name,
            'category_id' => $item->category_id,
            'category' => $item->itemCategory ? [
                'id' => $item->itemCategory->id,
                'name' => $item->itemCategory->name,
                'slug' => $item->itemCategory->slug,
                'type' => $item->itemCategory->type?->value ?? $item->itemCategory->type,
            ] : null,
            'unit' => $item->unit,
            'price' => $item->price !== null ? (float) $item->price : 0,
            'rental_price_per_day' => $item->rental_price_per_day !== null ? (float) $item->rental_price_per_day : 0,
            'images' => $item->galleryImages
                ->where('is_active', true)
                ->values()
                ->map(fn ($image): array => [
                    'id' => $image->id,
                    'file_name' => $image->file_name,
                    'size' => $image->size,
                    'dest' => '/'.$image->category_id.'/'.$image->file_name,
                    'mime_type' => $image->mime_type,
                ])
                ->all(),
        ];
    }

    private function transformLoanItem(LoanFormItem $item): array
    {
        $item->loadMissing(['inventory.warehouse', 'item']);
        $inventory = $item->inventory ?: InventoryItem::query()
            ->with(['warehouse', 'inventoryCondition'])
            ->where('sku', $item->sku)
            ->first();

        return [
            'id' => $item->id,
            'loan_form_code' => $item->loan_form_code,
            'sku' => $item->sku,
            'loan_item_name' => $item->loan_item_name,
            'rental_price_per_day' => (float) $item->rental_price_per_day,
            'item_price' => (float) $item->item_price,
            'inventory_id' => $item->inventory_id,
            'item_id' => $item->item_id,
            'item_type' => $item->item_type,
            'warehouse_id' => $item->warehouse_id,
            'size' => $item->size,
            'is_returned' => $item->is_returned,
            'created_by' => $item->created_by,
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
            'inventory' => $this->inventoryPayload($inventory),
            'item_detail' => $this->catalogItemPayload($item->item ?: $inventory?->item),
        ];
    }

    private function transformLoanForm(LoanForm $loanForm, bool $withRelations = true): array
    {
        $loanForm->loadMissing([
            'createdByEmployee',
            'updatedByEmployee',
            'items.inventory.warehouse',
            'items.item',
        ]);

        $payload = [
            'id' => $loanForm->id,
            'code' => $loanForm->code,
            'borrower_name' => $loanForm->borrower_name,
            'borrower_phone' => $loanForm->borrower_phone,
            'borrower_citizen_id_number' => $loanForm->borrower_citizen_id_number,
            'borrower_role' => $loanForm->borrower_role,
            'method' => $loanForm->method,
            'due_date' => $loanForm->due_date?->toISOString(),
            'deposit_amount' => (float) $loanForm->deposit_amount,
            'rental_days' => $loanForm->rental_days,
            'total_rental_amount' => (float) $loanForm->total_rental_amount,
            'total_item_price_amount' => (float) $loanForm->total_item_price_amount,
            'created_by' => $loanForm->created_by,
            'updated_by' => $loanForm->updated_by,
            'status' => $loanForm->status,
            'is_active' => $loanForm->is_active,
            'remark' => $loanForm->remark,
            'created_at' => $loanForm->created_at?->toISOString(),
            'updated_at' => $loanForm->updated_at?->toISOString(),
            'created_by_employee' => $this->employeePayload($loanForm->createdByEmployee),
            'updated_by_employee' => $this->employeePayload($loanForm->updatedByEmployee),
            'items' => $loanForm->items->sortBy('id')->values()->map(
                fn (LoanFormItem $item): array => $this->transformLoanItem($item)
            )->all(),
        ];

        if (! $withRelations) {
            return $payload;
        }

        $loanForm->loadMissing(['returnForms', 'penaltyForms', 'invoices']);

        return [
            ...$payload,
            'return_forms' => $loanForm->returnForms->sortByDesc('id')->values()->map(
                fn (ReturnForm $returnForm): array => $this->transformReturnForm($returnForm, false)
            )->all(),
            'penalty_forms' => $loanForm->penaltyForms->sortByDesc('id')->values()->map(
                fn (PenaltyForm $penaltyForm): array => $this->transformPenaltyForm($penaltyForm, false)
            )->all(),
            'invoices' => $loanForm->invoices->sortByDesc('id')->values()->map(
                fn (Invoice $invoice): array => $this->transformInvoice($invoice, false)
            )->all(),
        ];
    }

    private function transformReturnItem(ReturnFormItem $item): array
    {
        $item->loadMissing(['inventory.warehouse', 'item']);
        $inventory = $item->inventory ?: InventoryItem::query()
            ->with(['warehouse', 'inventoryCondition'])
            ->where('sku', $item->sku)
            ->first();

        return [
            'id' => $item->id,
            'return_form_code' => $item->return_form_code,
            'sku' => $item->sku,
            'return_item_name' => $item->return_item_name,
            'rental_price_per_day' => (float) $item->rental_price_per_day,
            'condition_on_return' => $item->condition_on_return,
            'inventory_id' => $item->inventory_id,
            'item_id' => $item->item_id,
            'item_type' => $item->item_type,
            'warehouse_id' => $item->warehouse_id,
            'size' => $item->size,
            'created_by' => $item->created_by,
            'is_active' => $item->is_active,
            'created_at' => $item->created_at?->toISOString(),
            'updated_at' => $item->updated_at?->toISOString(),
            'inventory' => $this->inventoryPayload($inventory),
            'item_detail' => $this->catalogItemPayload($item->item ?: $inventory?->item),
        ];
    }

    private function transformReturnForm(ReturnForm $returnForm, bool $withRelations = true): array
    {
        $returnForm->loadMissing([
            'createdByEmployee',
            'items.inventory.warehouse',
            'items.item',
            'loanForm',
        ]);

        $payload = [
            'id' => $returnForm->id,
            'code' => $returnForm->code,
            'loan_form_code' => $returnForm->loan_form_code,
            'loan_form' => $returnForm->loanForm ? $this->transformLoanForm($returnForm->loanForm, false) : null,
            'returnee_name' => $returnForm->returnee_name,
            'returnee_phone' => $returnForm->returnee_phone,
            'returnee_citizen_id_number' => $returnForm->returnee_citizen_id_number,
            'remark' => $returnForm->remark,
            'returnee_role' => $returnForm->returnee_role,
            'method' => $returnForm->method,
            'created_by' => $returnForm->created_by,
            'status' => $returnForm->status,
            'is_active' => $returnForm->is_active,
            'created_at' => $returnForm->created_at?->toISOString(),
            'updated_at' => $returnForm->updated_at?->toISOString(),
            'created_by_employee' => $this->employeePayload($returnForm->createdByEmployee),
            'items' => $returnForm->items->sortBy('id')->values()->map(
                fn (ReturnFormItem $item): array => $this->transformReturnItem($item)
            )->all(),
        ];

        if (! $withRelations) {
            return $payload;
        }

        $returnForm->loadMissing(['penaltyForms', 'invoices']);

        return [
            ...$payload,
            'penalty_forms' => $returnForm->penaltyForms->sortByDesc('id')->values()->map(
                fn (PenaltyForm $penaltyForm): array => $this->transformPenaltyForm($penaltyForm, false)
            )->all(),
            'invoices' => $returnForm->invoices->sortByDesc('id')->values()->map(
                fn (Invoice $invoice): array => $this->transformInvoice($invoice, false)
            )->all(),
        ];
    }

    private function transformPenaltyForm(PenaltyForm $penaltyForm, bool $withInvoices = true): array
    {
        $penaltyForm->loadMissing(['createdByEmployee', 'loanForm', 'returnForm.loanForm']);

        $payload = [
            'id' => $penaltyForm->id,
            'code' => $penaltyForm->code,
            'loan_form_code' => $penaltyForm->loan_form_code,
            'loan_form' => $penaltyForm->loanForm ? $this->transformLoanForm($penaltyForm->loanForm, false) : null,
            'return_form_code' => $penaltyForm->return_form_code,
            'return_form' => $penaltyForm->returnForm ? $this->transformReturnForm($penaltyForm->returnForm, false) : null,
            'reason' => $penaltyForm->reason,
            'amount' => (float) $penaltyForm->amount,
            'created_by' => $penaltyForm->created_by,
            'status' => $penaltyForm->status,
            'is_active' => $penaltyForm->is_active,
            'remark' => $penaltyForm->remark,
            'created_at' => $penaltyForm->created_at?->toISOString(),
            'updated_at' => $penaltyForm->updated_at?->toISOString(),
            'created_by_employee' => $this->employeePayload($penaltyForm->createdByEmployee),
        ];

        if (! $withInvoices) {
            return $payload;
        }

        $penaltyForm->loadMissing('invoices');

        return [
            ...$payload,
            'invoices' => $penaltyForm->invoices->sortByDesc('id')->values()->map(
                fn (Invoice $invoice): array => $this->transformInvoice($invoice, false)
            )->all(),
        ];
    }

    private function transformInvoice(Invoice $invoice, bool $withRelations = true): array
    {
        $invoice->loadMissing(['createdByEmployee']);

        $payload = [
            'id' => $invoice->id,
            'code' => $invoice->code,
            'loan_form_code' => $invoice->loan_form_code,
            'return_form_code' => $invoice->return_form_code,
            'penalty_form_code' => $invoice->penalty_form_code,
            'total_amount' => (float) $invoice->total_amount,
            'payment_amount' => (float) $invoice->payment_amount,
            'rental_amount' => (float) $invoice->rental_amount,
            'penalty_amount' => (float) $invoice->penalty_amount,
            'refund_amount' => (float) $invoice->refund_amount,
            'payment_method' => $invoice->payment_method,
            'payer_name' => $invoice->payer_name,
            'payer_phone' => $invoice->payer_phone,
            'payer_citizen_id_number' => $invoice->payer_citizen_id_number,
            'paid_at' => $invoice->paid_at?->toISOString(),
            'note' => $invoice->note,
            'created_by' => $invoice->created_by,
            'status' => $invoice->status,
            'is_active' => $invoice->is_active,
            'created_at' => $invoice->created_at?->toISOString(),
            'updated_at' => $invoice->updated_at?->toISOString(),
            'created_by_employee' => $this->employeePayload($invoice->createdByEmployee),
        ];

        if (! $withRelations) {
            return $payload;
        }

        $invoice->loadMissing(['loanForm', 'returnForm', 'penaltyForm']);

        return [
            ...$payload,
            'loan_form' => $invoice->loanForm ? $this->transformLoanForm($invoice->loanForm, false) : null,
            'return_form' => $invoice->returnForm ? $this->transformReturnForm($invoice->returnForm, false) : null,
            'penalty_form' => $invoice->penaltyForm ? $this->transformPenaltyForm($invoice->penaltyForm, false) : null,
        ];
    }
}
