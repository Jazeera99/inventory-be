<?php

namespace App\Utils\Permission;

use App\Traits\EnumValues;

enum Role: string
{
    use EnumValues;

    case SUPERADMIN = '1';
    case WAREHOUSE_MANAGER = '2';
    case STAFF_GUDANG = '3';
}
