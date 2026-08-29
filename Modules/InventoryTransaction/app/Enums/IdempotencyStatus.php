<?php

namespace Modules\InventoryTransaction\Enums;

enum IdempotencyStatus: string
{
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
}
