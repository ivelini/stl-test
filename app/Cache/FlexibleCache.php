<?php

namespace App\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Чтение с кэшем-окном и защитой от лавины (ADR 0007): flexible-фазы
 * [5, 15] + атомарная блокировка первого наполнения после инвалидации.
 * Инфраструктурный слой — единственное место, где сервисы трогают Cache::.
 */
class FlexibleCache
{
    /**
     * @param  array{0: int, 1: int}  $window  фазы окна: [свежие секунды, допустимая устарелость]
     * @param  callable(): mixed  $loader  загрузка данных при промахе
     */
    public function handle(string $key, array $window, callable $loader): mixed
    {
        return Cache::flexible($key, $window, function () use ($key, $loader) {
            return Cache::lock($key.':lock', (int) config('availability.cache_lock_seconds'))
                ->block((int) config('availability.cache_wait_seconds'), $loader);
        });
    }
}
