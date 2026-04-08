<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending        = 'pending';
    case PartiallyPaid  = 'partially_paid';  // NEW
    case Completed      = 'completed';
    case Cancelled      = 'cancelled';
    case Expired        = 'expired';
    // REMOVED: Processing, Failed, Refunded (these are now on Transaction)
}
