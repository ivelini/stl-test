<?php

namespace Tests\Unit\Services\Holds;

use App\Enums\HoldStatusEnum;
use App\Exceptions\CapacityExhaustedException;
use App\Models\Hold;
use App\Models\Slot;
use App\Services\SlotService\Holds\CreateHold;
use App\Services\SlotService\Slots\CheckCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateHoldTest extends TestCase
{
    use RefreshDatabase;

    private CreateHold $createHold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createHold = new CreateHold(new CheckCapacity);
    }

    private function insertConfirmedHold(Slot $slot, string $key): void
    {
        DB::table('holds')->insert([
            'slot_id' => $slot->id,
            'status' => 'confirmed',
            'idempotency_key' => $key,
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_create_creates_held_with_expiry(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);
        $key = (string) Str::uuid();

        $hold = $this->createHold->handle($slot, $key);

        $this->assertSame(HoldStatusEnum::Held, $hold->fresh()->status);
        $this->assertTrue(
            Carbon::parse($hold->fresh()->expires_at)->between(
                now()->addMinutes(5)->subSeconds(3),
                now()->addMinutes(5)->addSeconds(3)
            )
        );
    }

    public function test_create_same_key_returns_existing_no_new_row(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);
        $key = (string) Str::uuid();

        $first = $this->createHold->handle($slot, $key);
        $second = $this->createHold->handle($slot, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, DB::table('holds')->where('idempotency_key', $key)->count());
    }

    public function test_create_when_exhausted_throws_no_row(): void
    {
        $slot = Slot::query()->create(['capacity' => 1]);
        $this->insertConfirmedHold($slot, (string) Str::uuid());

        $this->expectException(CapacityExhaustedException::class);

        $this->createHold->handle($slot, (string) Str::uuid());

        $this->assertSame(1, Hold::where('slot_id', $slot->id)->count());
    }
}
