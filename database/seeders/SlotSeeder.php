<?php

namespace Database\Seeders;

use App\Models\Slot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlotSeeder extends Seeder
{
    private const COUNT = 10_000;

    private const CAPACITY_MIN = 1;

    private const CAPACITY_MAX = 10;

    public function run(): void
    {
        if (Slot::exists()) {
            $this->command?->warn('Таблица slots не пуста — сидер пропущен (используйте migrate:fresh).');

            return;
        }

        $now = now();
        $chunk = [];

        for ($i = 0; $i < self::COUNT; $i++) {
            $chunk[] = [
                'capacity' => random_int(self::CAPACITY_MIN, self::CAPACITY_MAX),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= 1_000) {
                DB::table('slots')->insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            DB::table('slots')->insert($chunk);
        }
    }
}
