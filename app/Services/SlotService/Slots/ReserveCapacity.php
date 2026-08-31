<?php

namespace App\Services\SlotService\Slots;

use App\Enums\HoldStatusEnum;
use App\Exceptions\CapacityExhaustedException;
use App\Exceptions\HoldStateConflictException;
use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Support\Facades\DB;

/**
 * Арбитраж вместимости: атомарный переход held → confirmed. Сериализация
 * конкурентных подтверждений на время «проверить → решить → записать»
 * (ADR 0002); решение — только по данным хранилища (ADR 0004).
 */
class ReserveCapacity
{
    public function handle(Slot $slot, Hold $hold): void
    {
        DB::transaction(function () use ($slot, $hold): void {
            $lockedSlot = Slot::whereKey($slot->id)->lockForUpdate()->firstOrFail();

            $confirmed = Hold::where('slot_id', $lockedSlot->id)
                ->where('status', HoldStatusEnum::Confirmed->value)
                ->count();

            if ($confirmed >= $lockedSlot->capacity) {
                throw new CapacityExhaustedException;
            }

            $affected = Hold::whereKey($hold->id)
                ->where('status', HoldStatusEnum::Held->value)
                ->update(['status' => HoldStatusEnum::Confirmed->value]);

            if ($affected === 0) {
                throw new HoldStateConflictException;
            }
        });
    }
}
