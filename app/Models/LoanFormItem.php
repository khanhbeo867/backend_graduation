<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanFormItem extends Model
{
    protected $fillable = [
        'is_active',
        'loan_form_code',
        'sku',
        'loan_item_name',
        'rental_price_per_day',
        'item_price',
        'inventory_id',
        'item_id',
        'item_type',
        'warehouse_id',
        'size',
        'is_returned',
        'created_by',
        'updated_by',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_returned' => 'boolean',
            'rental_price_per_day' => 'decimal:2',
            'item_price' => 'decimal:2',
        ];
    }

    public function loanForm(): BelongsTo
    {
        return $this->belongsTo(LoanForm::class, 'loan_form_code', 'code');
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_id', 'id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(EquipmentProp::class, 'item_id', 'id');
    }
}
