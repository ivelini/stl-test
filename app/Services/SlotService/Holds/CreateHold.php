<?php

namespace App\Services\SlotService\Holds;

use App\Models\Hold;
use App\Models\Slot;
use App\Services\SlotService\Slots\CheckCapacity;
use Illuminate\Database\QueryException;

/**
 * Выдача удержаний: ровно один билет на ключ идемпотентности, срок годности 5 минут.
 * Фабричный контракт: возвращает созданный/найденный билет (вариант B, согласован).
 */
class CreateHold
{
    public function __construct(
        private readonly CheckCapacity $checkCapacity,
    ) {}

    public function handle(Slot $slot, string $idempotencyKey): Hold
    {
        $existing = $this->findByKey($slot->id, $idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        $this->checkCapacity->handle($slot);

        try {
            return Hold::create([
                'slot_id' => $slot->id,
                'status' => 'held',
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addSeconds((int) config('availability.expires_seconds')),
            ]);
        } catch (QueryException $exception) {
            // Гонка: два создания с одним ключом — unique-индекс пропустил только первый.
            // Победитель уже закоммичен (insert блокировался до его коммита), отдаём его билет.
            return $this->findByKey($slot->id, $idempotencyKey) ?? throw $exception;
        }
    }

    private function findByKey(int $slotId, string $idempotencyKey): ?Hold
    {
        return Hold::where('slot_id', $slotId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }
}
