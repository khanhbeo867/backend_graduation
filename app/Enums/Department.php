<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Department: string
{
    use HasValues;

    case BOARD_OF_DIRECTORS = 'BOARD_OF_DIRECTORS';
    case ACCOUNTING_ADMIN = 'ACCOUNTING_ADMIN';
    case HR = 'HR';
    case EVENT_ORGANIZATION = 'EVENT_ORGANIZATION';
    case TECHNICAL = 'TECHNICAL';
}