<?php

namespace App\Services\SlotService\Holds;

use App\Enums\HoldStatusEnum;
use App\Events\SlotChangedEvent;
use App\Models\Hold;

/**
 * Отмена удержания: held | confirmed → cancelled, повтор — no-op.
 * Место «возвращается» автоматически — остаток вычисляется (ADR 0002).
 */
class CancelHold
{
    public function handle(Hold $hold): void
    {
        $fresh = $hold->fresh();

        if ($fresh->status === HoldStatusEnum::Cancelled) {
            return;
        }

        $affected = Hold::whereKey($fresh->id)
            ->whereIn('status', [HoldStatusEnum::Held->value, HoldStatusEnum::Confirmed->value])
            ->update(['status' => HoldStatusEnum::Cancelled->value]);

        if ($affected === 0) {
            return;
        }

        SlotChangedEvent::dispatch($fresh->slot_id);
    }
}
