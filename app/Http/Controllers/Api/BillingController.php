<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Api\Concerns\FormatsFrontendWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\LoanFormItem;
use App\Models\PenaltyForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    use FormatsFrontendWorkflow;

    public function penaltyIndex(Request $request): JsonResponse
    {
        $query = PenaltyForm::query()
            ->with(['createdByEmployee', 'loanForm.items', 'returnForm.loanForm', 'invoices']);

        $this->applySimpleFilters($query, $request, [
            'id',
            'code',
            'loan_form_code',
            'return_form_code',
            'status',
            'is_active',
            'created_by',
        ]);

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (PenaltyForm $penaltyForm): array => $this->transformPenaltyForm($penaltyForm))
                ->all()
        );
    }

    public function penaltyShow(int $id): JsonResponse
    {
        return $this->rawSuccess($this->transformPenaltyForm($this->findPenaltyForm($id)));
    }

    public function penaltyStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_form_code' => ['nullable', 'string', 'max:255'],
            'return_form_code' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['ISSUED', 'PAID', 'CANCELED'])],
            'remark' => ['nullable', 'string'],
        ]);

        $penaltyForm = PenaltyForm::query()->create([
            'code' => $this->nextWorkflowCode(PenaltyForm::class, 'PF'),
            'loan_form_code' => $data['loan_form_code'] ?? null,
            'return_form_code' => $data['return_form_code'] ?? null,
            'reason' => $data['reason'],
            'amount' => $data['amount'] ?? 0,
            'status' => $data['status'] ?? 'ISSUED',
            'remark' => $data['remark'] ?? null,
            'created_by' => $this->workflowEmployeeId($request),
            'is_active' => true,
        ]);

        return $this->rawSuccess($this->transformPenaltyForm($this->findPenaltyForm($penaltyForm->id)), Response::HTTP_CREATED);
    }

    public function penaltyUpdate(Request $request, int $id): JsonResponse
    {
        $penaltyForm = PenaltyForm::query()->findOrFail($id);

        $data = $request->validate([
            'loan_form_code' => ['nullable', 'string', 'max:255'],
            'return_form_code' => ['nullable', 'string', 'max:255'],
            'reason' => ['sometimes', 'required', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(['ISSUED', 'PAID', 'CANCELED'])],
            'remark' => ['nullable', 'string'],
        ]);

        $penaltyForm->update([
            ...$data,
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        return $this->rawSuccess($this->transformPenaltyForm($this->findPenaltyForm($id)));
    }

    public function penaltyDestroy(Request $request, int $id): JsonResponse
    {
        $penaltyForm = PenaltyForm::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $penaltyForm->delete();
        } else {
            $penaltyForm->update([
                'is_active' => false,
                'updated_by' => $this->workflowEmployeeId($request),
            ]);
        }

        return $this->rawSuccess(['message' => 'Penalty form deleted successfully']);
    }

    public function penaltyIssue(int $id): JsonResponse
    {
        PenaltyForm::query()->findOrFail($id)->update(['status' => 'ISSUED']);

        return $this->rawSuccess($this->transformPenaltyForm($this->findPenaltyForm($id)));
    }

    public function penaltyPay(int $id): JsonResponse
    {
        PenaltyForm::query()->findOrFail($id)->update(['status' => 'PAID']);

        return $this->rawSuccess($this->transformPenaltyForm($this->findPenaltyForm($id)));
    }

    public function invoiceIndex(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with(['createdByEmployee', 'loanForm.items', 'returnForm', 'penaltyForm']);

        $this->applySimpleFilters($query, $request, [
            'id',
            'code',
            'loan_form_code',
            'return_form_code',
            'penalty_form_code',
            'payment_method',
            'status',
            'is_active',
            'created_by',
        ]);

        return $this->rawSuccess(
            $query->latest('id')
                ->get()
                ->map(fn (Invoice $invoice): array => $this->transformInvoice($invoice))
                ->all()
        );
    }

    public function invoiceShow(int $id): JsonResponse
    {
        return $this->rawSuccess($this->transformInvoice($this->findInvoice($id)));
    }

    public function invoiceStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_form_code' => ['nullable', 'string', 'max:255'],
            'return_form_code' => ['nullable', 'string', 'max:255'],
            'penalty_form_code' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric'],
            'rental_amount' => ['nullable', 'numeric', 'min:0'],
            'penalty_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_amount' => ['nullable', 'numeric'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:50'],
            'payer_citizen_id_number' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'rental_days' => ['nullable', 'integer', 'min:1'],
            'extra_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(['ISSUED', 'UNCONFIRMED', 'PAID', 'CANCELED'])],
        ]);

        $loanForm = null;
        if (! empty($data['loan_form_code'])) {
            $loanForm = \App\Models\LoanForm::where('code', $data['loan_form_code'])->first();
        }

        $resolvedTotal = (float) ($data['total_amount'] ?? 0);
        if ($resolvedTotal <= 0) {
            if ($loanForm && $loanForm->method === 'BUY') {
                $resolvedTotal = (float) $loanForm->total_item_price_amount + (float) ($data['extra_amount'] ?? 0);
            } else {
                $resolvedTotal = $this->resolveRentalAmount($data['loan_form_code'] ?? null, (int) ($data['rental_days'] ?? 1))
                    + $this->resolvePenaltyAmount($data['penalty_form_code'] ?? null)
                    + (float) ($data['extra_amount'] ?? 0);
            }
        }

        $status = $data['status'] ?? 'ISSUED';

        $invoice = Invoice::query()->create([
            'code' => $this->nextWorkflowCode(Invoice::class, 'INV'),
            'loan_form_code' => $data['loan_form_code'] ?? null,
            'return_form_code' => $data['return_form_code'] ?? null,
            'penalty_form_code' => $data['penalty_form_code'] ?? null,
            'total_amount' => $resolvedTotal,
            'payment_amount' => $data['payment_amount'] ?? $resolvedTotal,
            'rental_amount' => $data['rental_amount'] ?? ($loanForm && $loanForm->method === 'BUY' ? 0 : $this->resolveRentalAmount($data['loan_form_code'] ?? null, (int) ($data['rental_days'] ?? 1))),
            'penalty_amount' => $data['penalty_amount'] ?? $this->resolvePenaltyAmount($data['penalty_form_code'] ?? null),
            'refund_amount' => $data['refund_amount'] ?? 0,
            'payment_method' => $data['payment_method'] ?? null,
            'payer_name' => $data['payer_name'] ?? null,
            'payer_phone' => $data['payer_phone'] ?? null,
            'payer_citizen_id_number' => $data['payer_citizen_id_number'] ?? null,
            'paid_at' => $data['paid_at'] ?? ($status === 'PAID' ? now() : null),
            'note' => $data['note'] ?? null,
            'created_by' => $this->workflowEmployeeId($request),
            'status' => $status,
            'is_active' => true,
        ]);

        if ($status === 'PAID') {
            $this->processInvoicePaid($invoice);
        }

        return $this->rawSuccess($this->transformInvoice($this->findInvoice($invoice->id)), Response::HTTP_CREATED);
    }

    public function invoiceUpdate(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        $data = $request->validate([
            'loan_form_code' => ['nullable', 'string', 'max:255'],
            'return_form_code' => ['nullable', 'string', 'max:255'],
            'penalty_form_code' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'payment_amount' => ['sometimes', 'numeric'],
            'rental_amount' => ['sometimes', 'numeric', 'min:0'],
            'penalty_amount' => ['sometimes', 'numeric', 'min:0'],
            'refund_amount' => ['sometimes', 'numeric'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'payer_name' => ['nullable', 'string', 'max:255'],
            'payer_phone' => ['nullable', 'string', 'max:50'],
            'payer_citizen_id_number' => ['nullable', 'string', 'max:50'],
            'paid_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['ISSUED', 'UNCONFIRMED', 'PAID', 'CANCELED'])],
        ]);

        $oldStatus = $invoice->status;
        $invoice->update([
            ...$data,
            'updated_by' => $this->workflowEmployeeId($request),
        ]);

        if (isset($data['status']) && $data['status'] === 'PAID' && $oldStatus !== 'PAID') {
            $this->processInvoicePaid($invoice->fresh());
        }

        return $this->rawSuccess($this->transformInvoice($this->findInvoice($id)));
    }

    public function invoiceDestroy(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        if ($this->shouldDeletePermanently($request)) {
            $invoice->delete();
        } else {
            $invoice->update([
                'is_active' => false,
                'updated_by' => $this->workflowEmployeeId($request),
            ]);
        }

        return $this->rawSuccess(['message' => 'Invoice deleted successfully']);
    }

    public function invoiceIssue(int $id): JsonResponse
    {
        Invoice::query()->findOrFail($id)->update(['status' => 'ISSUED']);

        return $this->rawSuccess($this->transformInvoice($this->findInvoice($id)));
    }

    public function invoicePay(int $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);
        $invoice->update([
            'status' => 'PAID',
            'paid_at' => $invoice->paid_at ?? now(),
            'payment_amount' => (float) $invoice->payment_amount > 0 ? $invoice->payment_amount : $invoice->total_amount,
        ]);

        $this->processInvoicePaid($invoice);

        return $this->rawSuccess($this->transformInvoice($this->findInvoice($id)));
    }

    public function customerPay(int $id): JsonResponse
    {
        $invoice = Invoice::query()->findOrFail($id);

        if ($invoice->status !== 'ISSUED') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'status' => ['Only issued invoices can be marked as customer paid pending confirmation'],
            ]);
        }

        $invoice->update([
            'status' => 'UNCONFIRMED',
        ]);

        return $this->rawSuccess($this->transformInvoice($this->findInvoice($id)));
    }

    private function updateInventoryStatusBySkus(array $skus, string $status): void
    {
        \App\Models\InventoryItem::query()
            ->whereIn('sku', array_unique(array_filter($skus)))
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
    }

    private function processInvoicePaid(Invoice $invoice): void
    {
        if ($invoice->penalty_form_code) {
            PenaltyForm::query()
                ->where('code', $invoice->penalty_form_code)
                ->update([
                    'status' => 'PAID',
                    'updated_at' => now(),
                ]);
        }

        if ($invoice->loan_form_code) {
            $loanForm = \App\Models\LoanForm::where('code', $invoice->loan_form_code)->first();
            if ($loanForm) {
                if ($loanForm->method === 'BUY') {
                    $loanForm->update(['status' => 'PAID']);
                    $this->updateInventoryStatusBySkus($loanForm->items->pluck('sku')->all(), \App\Enums\InventoryItemStatus::SOLD->value);
                } else {
                    $loanForm->update(['status' => 'BORROWING']);
                    $this->updateInventoryStatusBySkus($loanForm->items->pluck('sku')->all(), \App\Enums\InventoryItemStatus::RENTED->value);
                }
            }
        }
    }

    private function findPenaltyForm(int $id): PenaltyForm
    {
        return PenaltyForm::query()
            ->with(['createdByEmployee', 'loanForm.items', 'returnForm.loanForm', 'invoices'])
            ->findOrFail($id);
    }

    private function findInvoice(int $id): Invoice
    {
        return Invoice::query()
            ->with(['createdByEmployee', 'loanForm.items', 'returnForm', 'penaltyForm'])
            ->findOrFail($id);
    }

    private function resolveRentalAmount(?string $loanFormCode, int $rentalDays): float
    {
        if (! $loanFormCode) {
            return 0;
        }

        $days = max(1, $rentalDays);

        return (float) LoanFormItem::query()
            ->where('loan_form_code', $loanFormCode)
            ->get()
            ->sum(fn (LoanFormItem $item): float => (float) $item->rental_price_per_day * $days);
    }

    private function resolvePenaltyAmount(?string $penaltyFormCode): float
    {
        if (! $penaltyFormCode) {
            return 0;
        }

        return (float) (PenaltyForm::query()->where('code', $penaltyFormCode)->value('amount') ?? 0);
    }
}
