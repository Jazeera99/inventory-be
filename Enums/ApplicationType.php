<?php

namespace App\Enums;

use App\Traits\EnumValues;

enum ApplicationType: string
{
    use EnumValues;

    case WAREHOUSE = 'warehouse';
    case CASHIER = 'cashier';
    case ADMIN = 'admin';
    case USER = 'user';
}
