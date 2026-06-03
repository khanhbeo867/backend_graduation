<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory;

    protected $authPasswordName = 'password';

    protected $fillable = [
        'username',
        'password',
        'role',
        'employee_id',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRole(Builder $query, UserRole|string $role): Builder
    {
        $value = $role instanceof UserRole ? $role->value : strtoupper($role);

        return $query->where('role', $value);
    }

    public function hasRole(UserRole|string $role): bool
    {
        $currentRole = strtoupper((string) ($this->role?->value ?? $this->role));
        $expectedRole = $role instanceof UserRole ? $role->value : strtoupper($role);

        return $currentRole === $expectedRole;
    }

    public function hasAnyRole(array $roles): bool
    {
        $currentRole = strtoupper((string) ($this->role?->value ?? $this->role));

        $expectedRoles = array_map(
            fn (UserRole|string $role) => $role instanceof UserRole ? $role->value : strtoupper($role),
            $roles
        );

        return in_array($currentRole, $expectedRoles, true);
    }

    public function isEnabled(): bool
    {
        return $this->is_active === true;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role?->value,
            'username' => $this->username,
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'id');
    }

    public function employee(): BelongsTo
    {
        return $this->profile();
    }
}
