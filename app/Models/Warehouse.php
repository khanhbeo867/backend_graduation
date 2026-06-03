<?php

namespace App\Models;

use App\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $fillable = [
        'is_active',
        'code',
        'name',
        'type',
        'location',
        'manager_employee_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'type' => WarehouseType::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, WarehouseType|string $type): Builder
    {
        $value = $type instanceof WarehouseType ? $type->value : strtoupper($type);

        return $query->where('type', $value);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id', 'id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id', 'id');
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(EquipmentProp::class, 'warehouse_id', 'id');
    }
}
