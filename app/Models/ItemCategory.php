<?php

namespace App\Models;

use App\Enums\ItemCategoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemCategory extends Model
{
    protected $fillable = [
        'is_active',
        'name',
        'slug',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => ItemCategoryType::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(EquipmentProp::class, 'category_id', 'id');
    }

    public function equipmentProps(): HasMany
    {
        return $this->equipments();
    }

    public function costumes(): HasMany
    {
        return $this->equipments();
    }
}
