<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class SlotFactory extends Factory
{
    public function definition()
    {
        return [
            'capacity' => $this->faker->randomNumber(1),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
