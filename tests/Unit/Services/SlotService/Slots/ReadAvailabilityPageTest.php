<?php

namespace Tests\Unit\Services\SlotService\Slots;

use App\Cache\FlexibleCache;
use App\Cache\ReadCacheVersion;
use App\Services\SlotService\Slots\AvailabilityReader;
use App\Services\SlotService\Slots\ReadAvailabilityPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadAvailabilityPageTest extends TestCase
{
    use RefreshDatabase;

    private ReadAvailabilityPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->page = new ReadAvailabilityPage(
            new FlexibleCache,
            new ReadCacheVersion,
            new AvailabilityReader,
        );
    }

    private function createSlots(int $count): void
    {
        DB::table('slots')->insert(
            array_map(
                fn () => [
                    'capacity' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                range(1, $count)
            )
        );
    }

    public function test_first_page_returns_all_items_and_meta(): void
    {
        $this->createSlots(3);

        $result = $this->page->handle(1);

        $this->assertCount(3, $result['data']);
        $this->assertSame(1, $result['meta']['current_page']);
        $this->assertSame(100, $result['meta']['per_page']);
        $this->assertSame(3, $result['meta']['total']);
        $this->assertSame(1, $result['meta']['last_page']);
    }

    public function test_page_slice_and_last_page(): void
    {
        $this->createSlots(250);

        $page2 = $this->page->handle(2);
        $this->assertCount(100, $page2['data']);
        $this->assertSame(3, $page2['meta']['last_page']);

        $page3 = $this->page->handle(3);
        $this->assertCount(50, $page3['data']);
        $this->assertSame(3, $page3['meta']['current_page']);
    }

    public function test_page_beyond_last_returns_empty(): void
    {
        $this->createSlots(250);

        $result = $this->page->handle(999);

        $this->assertCount(0, $result['data']);
        $this->assertSame(999, $result['meta']['current_page']);
        $this->assertSame(250, $result['meta']['total']);
    }

    public function test_invalidation_bumps_version_and_refreshes_page(): void
    {
        $this->createSlots(3);
        $namespace = (string) config('availability.cache_key');

        $this->page->handle(1);
        $this->assertSame(0, Cache::get("{$namespace}:version", 0));

        (new \App\Cache\BumpCacheVersion)->handle($namespace);

        $this->assertSame(1, Cache::get("{$namespace}:version", 0));
        $this->assertCount(3, $this->page->handle(1)['data']);
    }
}
