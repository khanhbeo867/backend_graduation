<?php

namespace Database\Seeders;

use App\Models\InventoryCondition;
use Illuminate\Database\Seeder;

class InventoryConditionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaultConditions() as $condition) {
            InventoryCondition::query()->updateOrCreate(
                ['code' => $condition['code']],
                $condition
            );
        }
    }

    private function defaultConditions(): array
    {
        return [
            [
                'is_active' => true,
                'code' => 'A',
                'label' => 'Còn mới cho thuê',
                'discount_rate' => 0,
                'rentable' => true,
                'disposable' => false,
                'badge_color' => '#22c55e',
            ],
            [
                'is_active' => true,
                'code' => 'B',
                'label' => 'Cũ cho thanh lý',
                'discount_rate' => 0,
                'rentable' => false,
                'disposable' => true,
                'badge_color' => '#eab308',
            ],
        ];
    }
}
