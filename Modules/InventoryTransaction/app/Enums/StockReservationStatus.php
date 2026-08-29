<?php

namespace Modules\InventoryTransaction\Enums;

enum StockReservationStatus: string
{
    case ACTIVE = 'ACTIVE';
    case CONSUMED = 'CONSUMED';
    case RELEASED = 'RELEASED';
    case CANCELLED = 'CANCELLED';
    case EXPIRED = 'EXPIRED';
}
