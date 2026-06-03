<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\LoanForm;
use App\Models\LoanFormItem;
use App\Models\ReturnForm;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    private const ACTIVE_LOAN_STATUSES = ['BORROWING', 'DEPOSIT_PENDING'];

    public function index(Request $request): JsonResponse
    {
        $range = $this->normalizeRange($request->query('range'));
        $method = $this->normalizeMethod($request->query('method'));
        [$from, $to] = $this->timeRange($range);
        $today = now()->startOfDay();

        $loans = LoanForm::query()->get();
        $returns = ReturnForm::query()->get();
        $invoices = Invoice::query()->get();

        $loanByCode = $loans->keyBy('code');
        $returnByCode = $returns->keyBy('code');

        $allowedMethod = fn (?string $loanMethod): bool => $method === 'ALL' || strtoupper((string) $loanMethod) === $method;
        $inRange = fn ($value): bool => $value !== null
            && Carbon::parse($value)->betweenIncluded($from, $to);

        $filteredLoans = $loans->filter(
            fn (LoanForm $loan): bool => $allowedMethod($loan->method) && $inRange($loan->created_at)
        );

        $paidInvoices = $invoices->filter(function (Invoice $invoice) use ($inRange, $allowedMethod, $loanByCode, $returnByCode, $method): bool {
            if ($invoice->status !== 'PAID' || ! $inRange($invoice->paid_at ?? $invoice->created_at)) {
                return false;
            }

            $loan = null;
            if ($invoice->loan_form_code) {
                $loan = $loanByCode->get($invoice->loan_form_code);
            } elseif ($invoice->return_form_code) {
                $returnForm = $returnByCode->get($invoice->return_form_code);
                $loan = $returnForm?->loan_form_code ? $loanByCode->get($returnForm->loan_form_code) : null;
            }

            if ($loan) {
                return $allowedMethod($loan->method);
            }

            if ($method === 'ALL') {
                return (float) $invoice->rental_amount > 0;
            }

            return $method === 'RENT' && (float) $invoice->rental_amount > 0;
        });

        $returnedInRange = $returns
            ->map(function (ReturnForm $returnForm) use ($loanByCode): ?array {
                $loan = $loanByCode->get($returnForm->loan_form_code);

                return [
                    'status' => $returnForm->status,
                    'method' => $loan?->method ?? $returnForm->method,
                    'return_date' => $returnForm->created_at,
                    'due_date' => $loan?->due_date,
                ];
            })
            ->filter(fn (?array $row): bool => $row !== null)
            ->filter(fn (array $row): bool => $row['status'] === 'RETURNED'
                && $row['return_date'] !== null
                && $row['due_date'] !== null
                && Carbon::parse($row['return_date'])->betweenIncluded($from, $to)
                && $allowedMethod($row['method']));

        $onTimeCount = $returnedInRange->filter(
            fn (array $row): bool => Carbon::parse($row['return_date'])->endOfDay()
                ->lte(Carbon::parse($row['due_date'])->endOfDay())
        )->count();

        $dailyPoints = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $dayStart = $day->copy()->startOfDay();
            $dayEnd = $day->copy()->endOfDay();

            $dailyPoints[] = [
                'date' => $dayStart->format('d/m'),
                'revenue' => (float) $paidInvoices
                    ->filter(fn (Invoice $invoice): bool => Carbon::parse($invoice->paid_at ?? $invoice->created_at)->betweenIncluded($dayStart, $dayEnd))
                    ->sum(fn (Invoice $invoice): float => (float) $invoice->payment_amount),
                'newOrders' => $loans
                    ->filter(fn (LoanForm $loan): bool => $loan->created_at
                        && Carbon::parse($loan->created_at)->betweenIncluded($dayStart, $dayEnd)
                        && $allowedMethod($loan->method)
                        && in_array($loan->status, self::ACTIVE_LOAN_STATUSES, true))
                    ->count(),
            ];
        }

        $dueToday = $loans
            ->filter(fn (LoanForm $loan): bool => $allowedMethod($loan->method)
                && in_array($loan->status, self::ACTIVE_LOAN_STATUSES, true)
                && $loan->due_date
                && Carbon::parse($loan->due_date)->isSameDay($today))
            ->map(fn (LoanForm $loan): array => [
                'id' => $loan->id,
                'code' => $loan->code,
                'borrower_name' => $loan->borrower_name,
                'borrower_phone' => $loan->borrower_phone,
                'method' => $loan->method,
                'due_date' => $loan->due_date?->toISOString(),
                'status' => $loan->status,
            ])
            ->values()
            ->all();

        $filteredLoanCodes = $filteredLoans->pluck('code')->all();
        $ranked = LoanFormItem::query()
            ->whereIn('loan_form_code', $filteredLoanCodes)
            ->get()
            ->groupBy('sku')
            ->map(fn ($items, string $sku): array => [
                'sku' => $sku,
                'name' => (string) ($items->first()?->loan_item_name ?? $sku),
                'count' => $items->count(),
            ])
            ->sortByDesc('count')
            ->values();

        return $this->rawSuccess([
            'filters' => [
                'range' => $range,
                'method' => $method,
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
            ],
            'overviews' => [
                'revenue' => (float) $paidInvoices->sum(fn (Invoice $invoice): float => (float) $invoice->payment_amount),
                'newOrders' => $filteredLoans->filter(fn (LoanForm $loan): bool => in_array($loan->status, self::ACTIVE_LOAN_STATUSES, true))->count(),
                'onTimeRate' => $returnedInRange->count() > 0 ? ($onTimeCount / $returnedInRange->count()) * 100 : 0,
                'overdueOrders' => $loans->filter(fn (LoanForm $loan): bool => $allowedMethod($loan->method)
                    && in_array($loan->status, self::ACTIVE_LOAN_STATUSES, true)
                    && $loan->due_date
                    && Carbon::parse($loan->due_date)->startOfDay()->lt($today))->count(),
            ],
            'trendings' => [
                'revenueAndOrders' => $dailyPoints,
            ],
            'alerts' => [
                'dueToday' => $dueToday,
            ],
            'topList' => [
                'mostRented' => $ranked->take(5)->values()->all(),
                'leastRented' => $ranked->reverse()->take(5)->values()->all(),
            ],
        ]);
    }

    private function normalizeRange(mixed $value): string
    {
        return match (strtolower((string) $value)) {
            'today' => 'today',
            '30d' => '30d',
            'month' => 'month',
            default => '7d',
        };
    }

    private function normalizeMethod(mixed $value): string
    {
        return match (strtoupper((string) $value)) {
            'BORROW' => 'BORROW',
            'RENT' => 'RENT',
            default => 'ALL',
        };
    }

    private function timeRange(string $range): array
    {
        return match ($range) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '30d' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
        };
    }
}
