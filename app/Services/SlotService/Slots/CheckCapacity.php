<?php

namespace App\Services\SlotService\Slots;

use App\Enums\HoldStatusEnum;
use App\Exceptions\CapacityExhaustedException;
use App\Models\Hold;
use App\Models\Slot;

/**
 * Проверка места без резервирования: путь создания удержания, которое
 * место не занимает (ADR 0001) — гонка здесь не нарушает инвариант.
 */
class CheckCapacity
{
    public function handle(Slot $slot): void
    {
        $confirmed = Hold::where('slot_id', $slot->id)
            ->where('status', HoldStatusEnum::Confirmed->value)
            ->count();

        if ($confirmed >= $slot->capacity) {
            throw new CapacityExhaustedException;
        }
    }
}
