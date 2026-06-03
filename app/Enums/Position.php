<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Position: string
{
    use HasValues;

    case MANAGER = 'MANAGER';
    case WAREHOUSE_MANAGER = 'WAREHOUSE_MANAGER';
    case ORDER_PROCESSOR = 'ORDER_PROCESSOR';
    case TALENT = 'TALENT';
    case TECHICAL_CREW = 'TECHICAL_CREW';
    case TECHNICAL_CREW = 'TECHNICAL_CREW';
    case WARDROBE = 'WARDROBE';
    case SINGER = 'SINGER';
    case MC = 'MC';
    case RAPPER = 'RAPPER';
    case DANCER = 'DANCER';
    case CHOREOGRAPHER = 'CHOREOGRAPHER';
    case LOGISTICS_STAFF = 'LOGISTICS_STAFF';
    case SOUND_TECHNICIAN = 'SOUND_TECHNICIAN';
    case LIGHTING_TECHNICIAN = 'LIGHTING_TECHNICIAN';
    case WAREHOUSE_STAFF = 'WAREHOUSE_STAFF';
    case OFFICE_STAFF = 'OFFICE_STAFF';
}
