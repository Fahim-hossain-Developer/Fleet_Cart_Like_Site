<?php

namespace App\Domain\Payments\DTOs;

enum RemoteState: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';
}
