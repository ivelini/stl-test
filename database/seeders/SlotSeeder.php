<?php

namespace Database\Seeders;

use App\Models\Slot;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    public function run()
    {
        Slot::factory()
            ->count(10)
            ->create();
    }
}
