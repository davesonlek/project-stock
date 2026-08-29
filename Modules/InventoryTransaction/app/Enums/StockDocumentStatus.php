<?php

namespace Modules\InventoryTransaction\Enums;

enum StockDocumentStatus: string
{
    case DRAFT = 'DRAFT';
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case POSTED = 'POSTED';
    case REVERSED = 'REVERSED';
    case CANCELLED = 'CANCELLED';
}
