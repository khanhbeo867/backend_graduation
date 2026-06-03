<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCondition extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'label',
        'discount_rate',
        'rentable',
        'disposable',
        'badge_color',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'discount_rate' => 'decimal:2',
            'rentable' => 'boolean',
            'disposable' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'inventory_condition_id', 'id');
    }
}
