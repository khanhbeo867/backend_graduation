<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PenaltyForm extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'loan_form_code',
        'return_form_code',
        'reason',
        'amount',
        'created_by',
        'updated_by',
        'status',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'amount' => 'decimal:2',
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

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'penalty_form_code', 'code');
    }

    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by', 'id');
    }
}
