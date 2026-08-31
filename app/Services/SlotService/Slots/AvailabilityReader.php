<?php

namespace App\Services\SlotService\Slots;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Витрина доступности: остатки по странице. Остаток вычисляется в базе
 * одним запросом без гидратации моделей; страница — SQL LIMIT/OFFSET.
 */
class AvailabilityReader
{
    /**
     * Страница остатков: data — срез слотов с остатком, total — общее число слотов.
     *
     * @return array{
     *     data: array<int, array{slot_id: int, capacity: int, remaining: int}>,
     *     total: int
     * }
     */
    public function handlePage(int $page): array
    {
        $perPage = (int) config('availability.per_page');
        $offset = ($page - 1) * $perPage;

        $data = DB::table('slots')
            ->leftJoinSub(
                DB::table('holds')
                    // COUNT(*) — BIGINT UNSIGNED: вычитание из capacity ушло бы в unsigned
                    // и при нарушении инварианта (confirmed > capacity) упало бы с 1690.
                    // Явный CAST делает signed-семантику детерминированной.
                    ->selectRaw('slot_id, CAST(COUNT(*) AS SIGNED) as confirmed_count')
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
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (stdClass $row) => [
                'slot_id' => (int) $row->slot_id,
                'capacity' => (int) $row->capacity,
                'remaining' => (int) $row->remaining,
            ])
            ->all();

        return [
            'data' => $data,
            'total' => (int) DB::table('slots')->count(),
        ];
    }
}
