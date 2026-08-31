<?php

namespace Tests\Unit\Services\Holds;

use App\Enums\HoldStatusEnum;
use App\Events\SlotChangedEvent;
use App\Exceptions\HoldExpiredException;
use App\Models\Hold;
use App\Models\Slot;
use App\Services\SlotService\Holds\ConfirmHold;
use App\Services\SlotService\Slots\ReserveCapacity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConfirmHoldTest extends TestCase
{
    use RefreshDatabase;

    private ConfirmHold $confirmHold;

    protected function setUp(): void
    {
        parent::setUp();

        $this->confirmHold = new ConfirmHold(new ReserveCapacity);
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

    public function test_confirm_sets_confirmed_and_dispatches_event(): void
    {
        Event::fake([SlotChangedEvent::class]);
        $slot = Slot::query()->create(['capacity' => 10]);
        $hold = $this->createHold($slot);

        $this->confirmHold->handle($hold);

        $this->assertSame(HoldStatusEnum::Confirmed, $hold->fresh()->status);
        Event::assertDispatched(SlotChangedEvent::class, 1);
    }

    public function test_confirm_twice_is_idempotent_no_second_event(): void
    {
        Event::fake([SlotChangedEvent::class]);
        $slot = Slot::query()->create(['capacity' => 10]);
        $hold = $this->createHold($slot);

        $this->confirmHold->handle($hold);
        $this->confirmHold->handle($hold);

        Event::assertDispatched(SlotChangedEvent::class, 1);
        $this->assertSame(HoldStatusEnum::Confirmed, $hold->fresh()->status);
    }

    public function test_confirm_expired_hold_throws(): void
    {
        Event::fake([SlotChangedEvent::class]);
        $slot = Slot::query()->create(['capacity' => 10]);
        $hold = $this->createHold($slot, ['expires_at' => now()->subMinute()]);

        $this->expectException(HoldExpiredException::class);

        $this->confirmHold->handle($hold);

        $this->assertSame(HoldStatusEnum::Held, $hold->fresh()->status);
        Event::assertNotDispatched(SlotChangedEvent::class);
    }
}
