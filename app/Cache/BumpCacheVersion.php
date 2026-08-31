<?php

namespace App\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Инвалидация версионированного кэша: инкремент счётчика неймспейса.
 * O(1); старые ключи вида {namespace}:v{N-1}:* никто не читает — TTL уберёт.
 */
class BumpCacheVersion
{
    public function handle(string $namespace): void
    {
        Cache::increment($namespace.':version');
    }
}
