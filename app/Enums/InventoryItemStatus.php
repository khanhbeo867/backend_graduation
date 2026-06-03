<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum InventoryItemStatus: string
{
    use HasValues;

    case AVAILABLE = 'AVAILABLE';
    case RENTED = 'RENTED';
    case MAINTENANCE = 'MAINTENANCE';
    case LOST = 'LOST';
    case DISPOSED = 'DISPOSED';
}
