<?php

namespace Tests\Unit\Cache;

use App\Cache\ReadCacheVersion;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReadCacheVersionTest extends TestCase
{
    public function test_returns_zero_when_no_version(): void
    {
        $this->assertSame(0, (new ReadCacheVersion)->handle('read-test-namespace'));
    }

    public function test_returns_existing_version(): void
    {
        Cache::put('read-test-namespace:version', 3, 60);

        $this->assertSame(3, (new ReadCacheVersion)->handle('read-test-namespace'));
    }
}
