<?php

namespace App\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Текущая версия кэш-неймспейса. Версия участвует в ключе страниц:
 * инвалидация = инкремент (BumpCacheVersion), старые ключи недостижимы
 * и умирают по TTL.
 */
class ReadCacheVersion
{
    public function handle(string $namespace): int
    {
        return (int) Cache::get($namespace.':version', 0);
    }
}
