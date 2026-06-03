<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanForm extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'borrower_name',
        'borrower_phone',
        'borrower_citizen_id_number',
        'borrower_role',
        'method',
        'due_date',
        'rental_days',
        'total_rental_amount',
        'total_item_price_amount',
        'deposit_amount',
        'created_by',
        'updated_by',
        'status',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'due_date' => 'datetime',
            'rental_days' => 'integer',
            'total_rental_amount' => 'decimal:2',
            'total_item_price_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(LoanFormItem::class, 'loan_form_code', 'code');
    }

    public function returnForms(): HasMany
    {
        return $this->hasMany(ReturnForm::class, 'loan_form_code', 'code');
    }

    public function penaltyForms(): HasMany
    {
        return $this->hasMany(PenaltyForm::class, 'loan_form_code', 'code');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'loan_form_code', 'code');
    }

    public function createdByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by', 'id');
    }

    public function updatedByEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'updated_by', 'id');
    }
}
