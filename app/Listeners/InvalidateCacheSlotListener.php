<?php

namespace App\Listeners;

use App\Cache\BumpCacheVersion;
use App\Events\SlotChangedEvent;

/**
 * Слушатель — часть Витрины доступности (ADR 0006): инвалидация кэша
 * версией (инкремент) — старые ключи страниц недостижимы. Знание о кэше
 * остаётся в домене (неймспейс — config/availability.php), механизм — App\Cache.
 */
class InvalidateCacheSlotListener
{
    public function __construct(
        private readonly BumpCacheVersion $bumpVersion,
    ) {}

    public function handle(SlotChangedEvent $event): void
    {
        $this->bumpVersion->handle((string) config('availability.cache_key'));
    }
}
