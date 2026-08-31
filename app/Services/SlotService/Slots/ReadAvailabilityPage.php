<?php

namespace App\Services\SlotService\Slots;

use App\Cache\FlexibleCache;
use App\Cache\ReadCacheVersion;

/**
 * Витрина доступности: рабочий процесс чтения страницы остатков.
 * Кэш постраничный, ключ включает версию: инвалидация — инкремент версии
 * (BumpCacheVersion), старые ключи недостижимы. Контроллер — представление.
 */
class ReadAvailabilityPage
{
    public function __construct(
        private readonly FlexibleCache $cache,
        private readonly ReadCacheVersion $readVersion,
        private readonly AvailabilityReader $reader,
    ) {}

    /**
     * @return array{
     *     data: array<int, array{slot_id: int, capacity: int, remaining: int}>,
     *     meta: array{current_page: int, per_page: int, total: int, last_page: int}
     * }
     */
    public function handle(int $page): array
    {
        $namespace = (string) config('availability.cache_key');
        $version = $this->readVersion->handle($namespace);
        $key = "{$namespace}:v{$version}:p{$page}";

        $result = $this->cache->handle(
            $key,
            config('availability.cache_window'),
            fn () => $this->reader->handlePage($page),
        );

        $total = $result['total'];
        $perPage = (int) config('availability.per_page');

        return [
            'data' => $result['data'],
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }
}
