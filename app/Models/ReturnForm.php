<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnForm extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'loan_form_code',
        'returnee_name',
        'returnee_phone',
        'returnee_citizen_id_number',
        'remark',
        'returnee_role',
        'method',
        'created_by',
        'updated_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function loanForm(): BelongsTo
    {
        return $this->belongsTo(LoanForm::class, 'loan_form_code', 'code');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnFormItem::class, 'return_form_code', 'code');
    }

    public function penaltyForms(): HasMany
    {
        return $this->hasMany(PenaltyForm::class, 'return_form_code', 'code');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'return_form_code', 'code');
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
