<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SlotChangedEvent
{
    use Dispatchable;

    public function __construct(
        public int $slot_id,
    ) {}
}
