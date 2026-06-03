<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GalleryImage extends Model
{
    protected $fillable = [
        'is_active',
        'file_name',
        'mime_type',
        'size',
        'dest',
        'category_id',
        'created_by',
    ];

    protected $appends = [
        'url',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'size' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getUrlAttribute(): string
    {
        if ($this->file_name !== null && $this->file_name !== '') {
            return url('/storage/images-gallery/'.$this->category_id.'/'.ltrim($this->file_name, '/'));
        }

        $path = parse_url((string) $this->dest, PHP_URL_PATH);

        if (is_string($path) && $path !== '') {
            return url('/'.ltrim($path, '/'));
        }

        return (string) $this->dest;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'category_id', 'id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function equipmentProps(): BelongsToMany
    {
        return $this->belongsToMany(
            EquipmentProp::class,
            'equipment_prop_gallery_image',
            'gallery_image_id',
            'equipment_prop_id'
        )->withTimestamps();
    }
}
