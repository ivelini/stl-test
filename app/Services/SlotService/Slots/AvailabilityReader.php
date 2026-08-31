<?php

namespace App\Services\SlotService\Slots;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Витрина доступности: представление остатков. Остаток вычисляется в базе
 * одним запросом без гидратации моделей. Настройки кэша — в config/availability.php.
 */
class AvailabilityReader
{
    /**
     * Полная карта остатков: capacity − количество подтверждённых (ADR 0002),
     * вычитание — в SQL. Пагинация — срез после кэша (план, вариант A).
     *
     * @return array<int, array{slot_id: int, capacity: int, remaining: int}>
     */
    public function handle(): array
    {
        return DB::table('slots')
            ->leftJoinSub(
                DB::table('holds')
                    ->selectRaw('slot_id, COUNT(*) as confirmed_count')
                    ->where('status', 'confirmed')
                    ->groupBy('slot_id'),
                'confirmed',
                'confirmed.slot_id',
                '=',
                'slots.id'
            )
            ->selectRaw(
                'slots.id as slot_id, slots.capacity, '
                .'slots.capacity - COALESCE(confirmed.confirmed_count, 0) as remaining'
            )
            ->orderBy('slots.id')
            ->get()
            ->map(fn (stdClass $row) => [
                'slot_id' => (int) $row->slot_id,
                'capacity' => (int) $row->capacity,
                'remaining' => (int) $row->remaining,
            ])
            ->all();
    }
}
