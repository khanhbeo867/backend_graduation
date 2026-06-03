<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum UserRole: string
{
    use HasValues;

    case ADMIN = 'ADMIN';
    case USER = 'USER';
    case MANAGER = 'MANAGER';
    case HR_STAFF = 'HR_STAFF';
    case WAREHOUSE_STAFF = 'WAREHOUSE_STAFF';
}
