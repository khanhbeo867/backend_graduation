<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentMethod: string
{
    use HasValues;

    case CASH = 'CASH';
    case BANK_TRANSFER = 'BANK_TRANSFER';
    case CARD = 'CARD';
    case E_WALLET = 'E_WALLET';
    case OTHER = 'OTHER';
}