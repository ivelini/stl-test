<?php

namespace Tests\Unit\Services\Holds;

use App\Enums\HoldStatusEnum;
use App\Events\SlotChangedEvent;
use App\Models\Hold;
use App\Models\Slot;
use App\Services\SlotService\Holds\CancelHold;
use App\Services\SlotService\Holds\ConfirmHold;
use App\Services\SlotService\Slots\ReserveCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CancelHoldTest extends TestCase
{
    use RefreshDatabase;

    private CancelHold $cancelHold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cancelHold = new CancelHold;
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

    public function test_cancel_from_held_and_confirmed(): void
    {
        Event::fake([SlotChangedEvent::class]);
        $slot = Slot::query()->create(['capacity' => 10]);
        $held = $this->createHold($slot);
        $confirmed = $this->createHold($slot);

        (new ConfirmHold(new ReserveCapacity))->handle($confirmed);
        $this->cancelHold->handle($held);
        $this->cancelHold->handle($confirmed);

        $this->assertSame(HoldStatusEnum::Cancelled, $held->fresh()->status);
        $this->assertSame(HoldStatusEnum::Cancelled, $confirmed->fresh()->status);
        Event::assertDispatched(SlotChangedEvent::class, 3);
    }

    public function test_cancel_twice_is_noop(): void
    {
        Event::fake([SlotChangedEvent::class]);
        $slot = Slot::query()->create(['capacity' => 10]);
        $hold = $this->createHold($slot);

        $this->cancelHold->handle($hold);
        $this->cancelHold->handle($hold);

        $this->assertSame(HoldStatusEnum::Cancelled, $hold->fresh()->status);
        Event::assertDispatched(SlotChangedEvent::class, 1);
    }
}
