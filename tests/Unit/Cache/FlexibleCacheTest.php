<?php

namespace Tests\Unit\Cache;

use App\Cache\CacheInvalidator;
use App\Cache\FlexibleCache;
use Tests\TestCase;

class FlexibleCacheTest extends TestCase
{
    public function test_first_read_loads_then_cached_loader_once(): void
    {
        $cache = new FlexibleCache;
        $calls = 0;

        $first = $cache->handle('flexible-test-key-1', [5, 15], function () use (&$calls) {
            $calls++;

            return ['data' => 1];
        });
        $second = $cache->handle('flexible-test-key-1', [5, 15], function () use (&$calls) {
            $calls++;

            return ['data' => 1];
        });

        $this->assertSame(1, $calls);
        $this->assertSame($first, $second);
    }

    public function test_after_invalidate_loader_runs_again(): void
    {
        $cache = new FlexibleCache;
        $calls = 0;
        $loader = function () use (&$calls) {
            $calls++;

            return ['data' => 1];
        };

        $cache->handle('flexible-test-key-2', [5, 15], $loader);
        (new CacheInvalidator)->handle('flexible-test-key-2');
        $cache->handle('flexible-test-key-2', [5, 15], $loader);

        $this->assertSame(2, $calls);
    }
}
