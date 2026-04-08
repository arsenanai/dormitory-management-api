<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending    = 'pending';
    case Processing = 'processing';   // bank check uploaded, awaiting admin verification
    case Completed  = 'completed';    // verified by admin or confirmed by gateway
    case Failed     = 'failed';
    case Cancelled  = 'cancelled';
    case Refunded   = 'refunded';
}
