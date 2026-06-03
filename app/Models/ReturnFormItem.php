<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnFormItem extends Model
{
    protected $fillable = [
        'is_active',
        'return_form_code',
        'sku',
        'return_item_name',
        'rental_price_per_day',
        'condition_on_return',
        'inventory_id',
        'item_id',
        'item_type',
        'warehouse_id',
        'size',
        'created_by',
        'updated_by',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rental_price_per_day' => 'decimal:2',
        ];
    }

    public function returnForm(): BelongsTo
    {
        return $this->belongsTo(ReturnForm::class, 'return_form_code', 'code');
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
