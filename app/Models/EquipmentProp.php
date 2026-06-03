<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentProp extends Model
{
    protected $fillable = [
        'is_active',
        'sku',
        'name',
        'slug',
        'color',
        'sizes',
        'gender',
        'category_id',
        'unit',
        'price',
        'warehouse_id',
        'rental_price_per_day',
        'weight_kg',
        'dimensions',
        'is_fragile',
        'hashtags',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'color' => 'array',
            'sizes' => 'array',
            'gender' => Gender::class,
            'price' => 'decimal:2',
            'rental_price_per_day' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'dimensions' => 'array',
            'is_fragile' => 'boolean',
            'hashtags' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id', 'id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id', 'id');
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'item_id', 'id');
    }

    public function galleryImages(): BelongsToMany
    {
        return $this->belongsToMany(
            GalleryImage::class,
            'equipment_prop_gallery_image',
            'equipment_prop_id',
            'gallery_image_id'
        )->withTimestamps();
    }
}
