<?php

namespace Tests\Unit\Cache;

use App\Cache\CacheInvalidator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidatorTest extends TestCase
{
    public function test_handle_forgets_key(): void
    {
        Cache::put('invalidator-test-key', 'value', 60);

        (new CacheInvalidator)->handle('invalidator-test-key');

        $this->assertFalse(Cache::has('invalidator-test-key'));
    }
}
