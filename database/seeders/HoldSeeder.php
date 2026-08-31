<?php

namespace Database\Seeders;

use App\Enums\HoldStatusEnum;
use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Удержания к слотам: confirmed в пределах вместимости (инвариант
 * «остаток ≥ 0», ADR 0002), плюс немного held и cancelled для картины.
 */
class HoldSeeder extends Seeder
{
    public function run(): void
    {
        if (Hold::exists()) {
            $this->command?->warn('Таблица holds не пуста — сидер пропущен (используйте migrate:fresh).');

            return;
        }

        $now = now();
        $expires = now()->addMinutes((int) config('availability.expires_minutes'));
        $chunk = [];

        foreach (Slot::query()->orderBy('id')->pluck('capacity', 'id') as $slotId => $capacity) {
            for ($i = 0; $i < random_int(0, $capacity); $i++) {
                $chunk[] = $this->hold($slotId, HoldStatusEnum::Confirmed->value, $now, $expires);
            }

            for ($i = 0; $i < random_int(0, 2); $i++) {
                $chunk[] = $this->hold($slotId, HoldStatusEnum::Held->value, $now, $expires);
            }

            for ($i = 0; $i < random_int(0, 2); $i++) {
                $chunk[] = $this->hold($slotId, HoldStatusEnum::Cancelled->value, $now, $expires);
            }

            if (count($chunk) >= 1_000) {
                DB::table('holds')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table('holds')->insert($chunk);
        }
    }

    /** @return array<string, mixed> */
    private function hold(int $slotId, string $status, Carbon $now, Carbon $expires): array
    {
        return [
            'slot_id' => $slotId,
            'status' => $status,
            'idempotency_key' => (string) Str::uuid(),
            'expires_at' => $expires,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
