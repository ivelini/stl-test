<?php

namespace App\Listeners;

use App\Events\SlotChangedEvent;

class InvalidateCacheSlotListener
{
    public function __construct() {}

    public function handle(SlotChangedEvent $event): void {}
}
