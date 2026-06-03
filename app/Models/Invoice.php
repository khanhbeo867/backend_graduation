<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'loan_form_code',
        'return_form_code',
        'penalty_form_code',
        'total_amount',
        'payment_amount',
        'rental_amount',
        'penalty_amount',
        'refund_amount',
        'payment_method',
        'payer_name',
        'payer_phone',
        'payer_citizen_id_number',
        'paid_at',
        'note',
        'created_by',
        'updated_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'total_amount' => 'decimal:2',
            'payment_amount' => 'decimal:2',
            'rental_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function loanForm(): BelongsTo
    {
        return $this->belongsTo(LoanForm::class, 'loan_form_code', 'code');
    }

    public function returnForm(): BelongsTo
    {
        return $this->belongsTo(ReturnForm::class, 'return_form_code', 'code');
    }

    public function penaltyForm(): BelongsTo
    {
        return $this->belongsTo(PenaltyForm::class, 'penalty_form_code', 'code');
    }

    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by', 'id');
    }
}
