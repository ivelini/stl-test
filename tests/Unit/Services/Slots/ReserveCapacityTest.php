<?php

namespace Tests\Unit\Services\Slots;

use App\Enums\HoldStatusEnum;
use App\Exceptions\CapacityExhaustedException;
use App\Exceptions\HoldStateConflictException;
use App\Models\Hold;
use App\Models\Slot;
use App\Services\SlotService\Holds\CancelHold;
use App\Services\SlotService\Slots\ReserveCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReserveCapacityTest extends TestCase
{
    use RefreshDatabase;

    private ReserveCapacity $reserveCapacity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reserveCapacity = new ReserveCapacity;
    }

    /** @param array<string, mixed> $overrides */
    private function createHold(Slot $slot, array $overrides = []): Hold
    {
        $id = DB::table('holds')->insertGetId([
            'slot_id' => $slot->id,
            'status' => 'held',
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);

        return Hold::findOrFail($id);
    }

    private function insertConfirmedHolds(Slot $slot, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('holds')->insert([
                'slot_id' => $slot->id,
                'status' => 'confirmed',
                'idempotency_key' => (string) Str::uuid(),
                'expires_at' => now()->addMinutes(5),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_reserve_when_equal_to_capacity_throws(): void
    {
        $slot = Slot::query()->create(['capacity' => 2]);
        $this->insertConfirmedHolds($slot, 2);
        $hold = $this->createHold($slot);

        $this->expectException(CapacityExhaustedException::class);

        $this->reserveCapacity->handle($slot, $hold);
    }

    public function test_reserve_only_held_hold_transitions(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);
        $hold = $this->createHold($slot);

        (new CancelHold)->handle($hold);

        $this->expectException(HoldStateConflictException::class);

        $this->reserveCapacity->handle($slot, $hold);

        $this->assertSame(HoldStatusEnum::Cancelled, $hold->fresh()->status);
    }
}
