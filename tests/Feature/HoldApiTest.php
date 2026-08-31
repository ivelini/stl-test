<?php

namespace Tests\Feature;

use App\Events\SlotChangedEvent;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class HoldApiTest extends TestCase
{
    use RefreshDatabase;

    private function createHoldViaApi(Slot $slot): int
    {
        $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/slots/{$slot->id}/hold");

        $response->assertStatus(201);

        return $response->json('id');
    }

    /** @param array<string, mixed> $overrides */
    private function insertHold(Slot $slot, array $overrides = []): int
    {
        return DB::table('holds')->insertGetId([
            'slot_id' => $slot->id,
            'status' => 'held',
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => now()->addMinutes(5),
            'created_at' => now(),
            'updated_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_hold_created_then_replay_same_key(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);
        $key = (string) Str::uuid();

        $created = $this->withHeader('Idempotency-Key', $key)->postJson("/api/slots/{$slot->id}/hold");
        $created->assertStatus(201)->assertJsonPath('status', 'held');

        $replay = $this->withHeader('Idempotency-Key', $key)->postJson("/api/slots/{$slot->id}/hold");
        $replay->assertStatus(200)->assertJsonPath('id', $created->json('id'));
    }

    public function test_hold_without_key_422(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);

        $this->postJson("/api/slots/{$slot->id}/hold")->assertStatus(422);
    }

    public function test_confirm_then_confirm_again_200_single_event(): void
    {
        Event::fake([SlotChangedEvent::class]);
        $slot = Slot::query()->create(['capacity' => 10]);
        $holdId = $this->createHoldViaApi($slot);

        $this->postJson("/api/holds/{$holdId}/confirm")->assertStatus(200);
        $this->postJson("/api/holds/{$holdId}/confirm")->assertStatus(200);

        Event::assertDispatched(SlotChangedEvent::class, 1);
    }

    public function test_confirm_over_capacity_409(): void
    {
        $slot = Slot::query()->create(['capacity' => 1]);
        $first = $this->createHoldViaApi($slot);
        $second = $this->createHoldViaApi($slot);

        $this->postJson("/api/holds/{$first}/confirm")->assertStatus(200);
        $this->postJson("/api/holds/{$second}/confirm")->assertStatus(409);
    }

    public function test_confirm_expired_hold_422(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);
        $holdId = $this->insertHold($slot, ['expires_at' => now()->subMinute()]);

        $this->postJson("/api/holds/{$holdId}/confirm")->assertStatus(422);

        $this->assertSame('held', DB::table('holds')->where('id', $holdId)->value('status'));
    }

    public function test_delete_then_delete_again_204_row_cancelled(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);
        $holdId = $this->createHoldViaApi($slot);

        $this->deleteJson("/api/holds/{$holdId}")->assertStatus(204);
        $this->deleteJson("/api/holds/{$holdId}")->assertStatus(204);

        $this->assertSame('cancelled', DB::table('holds')->where('id', $holdId)->value('status'));
        $this->assertNotNull(DB::table('holds')->where('id', $holdId)->first());
    }

    public function test_confirm_and_cancel_invalidate_cache(): void
    {
        $slot = Slot::query()->create(['capacity' => 10]);

        Cache::put(config('availability.cache_key'), 'stale', 60);
        $holdId = $this->createHoldViaApi($slot);
        $this->postJson("/api/holds/{$holdId}/confirm")->assertStatus(200);
        $this->assertFalse(Cache::has(config('availability.cache_key')));

        Cache::put(config('availability.cache_key'), 'stale', 60);
        $holdId = $this->createHoldViaApi($slot);
        $this->deleteJson("/api/holds/{$holdId}")->assertStatus(204);
        $this->assertFalse(Cache::has(config('availability.cache_key')));
    }
}
