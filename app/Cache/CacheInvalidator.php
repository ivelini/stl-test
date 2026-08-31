<?php

namespace App\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Сброс ключа кэша. Инфраструктурный слой — единственное место
 * с Cache::-фасадом (правило: сервисы не трогают кэш напрямую).
 */
class CacheInvalidator
{
    public function handle(string $key): void
    {
        Cache::forget($key);
    }
}
