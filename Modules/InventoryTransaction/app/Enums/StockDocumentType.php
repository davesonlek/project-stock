<?php

namespace Modules\InventoryTransaction\Enums;

enum StockDocumentType: string
{
    case RECEIVE = 'RECEIVE';
    case ISSUE = 'ISSUE';
    case TRANSFER = 'TRANSFER';
    case ADJUSTMENT = 'ADJUSTMENT';
    case REVERSAL = 'REVERSAL';
}
