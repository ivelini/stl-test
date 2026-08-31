<?php

namespace App\Enums;

enum HoldStatusEnum: string
{
    case Held = 'held';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
}
