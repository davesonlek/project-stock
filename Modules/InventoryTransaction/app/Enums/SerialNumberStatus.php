<?php

namespace Modules\InventoryTransaction\Enums;

enum SerialNumberStatus: string
{
    case IN_STOCK = 'IN_STOCK';
    case RESERVED = 'RESERVED';
    case ISSUED = 'ISSUED';
    case DAMAGED = 'DAMAGED';
    case MISSING = 'MISSING';
    case REVERSED = 'REVERSED';
}

