<?php

namespace Tests\Unit\Cache;

use App\Cache\BumpCacheVersion;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BumpCacheVersionTest extends TestCase
{
    public function test_handle_increments_version_from_zero(): void
    {
        (new BumpCacheVersion)->handle('bump-test-namespace');

        $this->assertSame(1, Cache::get('bump-test-namespace:version'));
    }

    public function test_handle_increments_existing_version(): void
    {
        Cache::put('bump-test-namespace:version', 5, 60);

        (new BumpCacheVersion)->handle('bump-test-namespace');

        $this->assertSame(6, Cache::get('bump-test-namespace:version'));
    }
}
