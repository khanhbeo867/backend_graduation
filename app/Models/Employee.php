<?php

namespace App\Models;

use App\Enums\Position;
use App\Enums\Work;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    protected $fillable = [
        'is_active',
        'user_id',
        'employee_code',
        'full_name',
        'email',
        'phone',
        'address',
        'citizen_id_number',
        'position',
        'hire_date',
        'work_status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => Position::class,
            'hire_date' => 'date',
            'work_status' => Work::class,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePosition(Builder $query, Position|string $position): Builder
    {
        $value = $position instanceof Position ? $position->value : strtoupper($position);

        return $query->where('position', $value);
    }

    public function scopeWorkStatus(Builder $query, Work|string $status): Builder
    {
        $value = $status instanceof Work ? $status->value : strtoupper($status);

        return $query->where('work_status', $value);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id', 'id');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class, 'manager_employee_id', 'id');
    }
}
