<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EquipmentStatus: string
{
    use HasValues;

    case AVAILABLE = 'AVAILABLE';
    case IN_USE = 'IN_USE';
    case DAMAGED = 'DAMAGED';
    case MAINTENANCE = 'MAINTENANCE';
    case LOST = 'LOST';
    case LIQUIDATED = 'LIQUIDATED';
}