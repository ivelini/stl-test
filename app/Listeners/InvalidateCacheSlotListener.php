<?php

namespace App\Listeners;

use App\Cache\CacheInvalidator;
use App\Events\SlotChangedEvent;

/**
 * Слушатель — часть Витрины доступности (ADR 0006): знание о кэше
 * остаётся в домене (ключ — в config/availability.php), механизм — в App\Cache.
 */
class InvalidateCacheSlotListener
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
    ) {}

    public function handle(SlotChangedEvent $event): void
    {
        $this->cacheInvalidator->handle(config('availability.cache_key'));
    }
}
