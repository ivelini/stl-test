<?php

namespace Tests\Unit\Services\SlotService\Slots;

use App\Models\Slot;
use App\Services\SlotService\Slots\AvailabilityReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AvailabilityReaderTest extends TestCase
{
    use RefreshDatabase;

    private AvailabilityReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new AvailabilityReader;
    }

    private function insertHold(Slot $slot, string $status): void
    {
        DB::table('holds')->insert([
            'slot_id' => $slot->id,
            'status' => $status,
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function remainingFor(Slot $slot): array
    {
        return collect($this->reader->handlePage(1)['data'])->firstWhere('slot_id', $slot->id);
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

    public function test_remaining_computed_in_database(): void
    {
        $slot = Slot::query()->create(['capacity' => 3]);
        $this->insertHold($slot, 'confirmed');

        $this->assertSame(2, $this->remainingFor($slot)['remaining']);
        $this->assertSame(3, $this->remainingFor($slot)['capacity']);
    }

    public function test_remaining_ignores_holds(): void
    {
        $slot = Slot::query()->create(['capacity' => 1]);
        for ($i = 0; $i < 5; $i++) {
            $this->insertHold($slot, 'held');
        }

        $this->assertSame(1, $this->remainingFor($slot)['remaining']);
    }

    /**
     * Страж-тест долга: COUNT(*) — BIGINT UNSIGNED, вычитание из capacity
     * могло упасть с ERROR 1690 (переполнение unsigned). Реализация с
     * CAST(COUNT(*) AS SIGNED) обязана вернуть честный отрицательный остаток,
     * а не уронить путь чтения.
     */
    public function test_negative_remaining_when_confirmed_exceeds_capacity(): void
    {
        $slot = Slot::query()->create(['capacity' => 1]);
        $this->insertHold($slot, 'confirmed');
        $this->insertHold($slot, 'confirmed');

        $this->assertSame(-1, $this->remainingFor($slot)['remaining']);
    }

    public function test_page_returns_only_page_rows_and_total(): void
    {
        $this->createSlots(250);

        $page2 = $this->reader->handlePage(2);

        $this->assertCount(100, $page2['data']);
        $this->assertSame(101, $page2['data'][0]['slot_id']);
        $this->assertSame(250, $page2['total']);
    }
}
