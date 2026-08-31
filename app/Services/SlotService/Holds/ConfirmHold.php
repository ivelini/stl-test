<?php

namespace App\Services\SlotService\Holds;

use App\Enums\HoldStatusEnum;
use App\Events\SlotChangedEvent;
use App\Exceptions\HoldExpiredException;
use App\Models\Hold;
use App\Services\SlotService\Slots\ReserveCapacity;
use Illuminate\Support\Carbon;

/**
 * Подтверждение удержания: переход held → confirmed. Просрочка — производная
 * от expires_at (статуса нет); повтор подтверждённого — идемпотентен.
 * После перехода публикует факт SlotChanged — кэшем не владеет (ADR 0006).
 */
class ConfirmHold
{
    public function __construct(
        private readonly ReserveCapacity $reserveCapacity,
    ) {}

    public function handle(Hold $hold): void
    {
        $fresh = $hold->fresh();

        if ($fresh->status === HoldStatusEnum::Confirmed) {
            return;
        }

        if (Carbon::parse($fresh->expires_at)->isPast()) {
            throw new HoldExpiredException;
        }

        $this->reserveCapacity->handle($fresh->slot, $fresh);

        SlotChangedEvent::dispatch($fresh->slot_id);
    }
}
