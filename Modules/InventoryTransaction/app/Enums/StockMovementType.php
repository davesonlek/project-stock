<?php

namespace Modules\InventoryTransaction\Enums;

enum StockMovementType: string
{
    case RECEIVE = 'RECEIVE';
    case ISSUE = 'ISSUE';
    case TRANSFER_OUT = 'TRANSFER_OUT';
    case TRANSFER_IN = 'TRANSFER_IN';
    case ADJUST_IN = 'ADJUST_IN';
    case ADJUST_OUT = 'ADJUST_OUT';
    case REVERSAL = 'REVERSAL';
}
