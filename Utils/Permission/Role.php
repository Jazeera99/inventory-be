<?php

namespace App\Utils\Permission;

use App\Traits\EnumValues;

enum Role: string
{
    use EnumValues;

    case SUPERADMIN = 'Superadmin'; // access admin and warehouse app, only meily can delete sale
    case STORE_MANAGER = 'Supervisor'; // password used to authorize sale discount and stock adjustment on 1 branch
    case SPG = 'Kasir/SPG'; // access cashier app, stock adjustment and discount need password from STORE_MANAGER or RETAIL_MANAGER or SUPERADMIN
    case WAREHOUSE = 'Admin Gudang'; // access warehouse app
    case RETAIL_MANAGER = 'Staff Toko'; // password used to authorize stock adjustment and discount on all branches
    case MEMBER = 'Member';
}
