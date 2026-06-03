<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Gender: string
{
    use HasValues;

    case MALE = 'MALE';
    case FEMALE = 'FEMALE';
    case UNKNOWN = 'UNKNOWN';
}