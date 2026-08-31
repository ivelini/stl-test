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
        return collect($this->reader->handle())->firstWhere('slot_id', $slot->id);
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
}
