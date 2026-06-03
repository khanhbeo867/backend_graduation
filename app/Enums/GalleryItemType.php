<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum GalleryItemType: string
{
    use HasValues;

    case COSTUME = 'COSTUME';
    case EQUIPMENT_PROPS = 'EQUIPMENT_PROPS';
}
