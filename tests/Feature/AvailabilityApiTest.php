<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilityApiTest extends TestCase
{
    use RefreshDatabase;

    private function createSlots(int $count): void
    {
        DB::table('slots')->insert(
            array_map(
                fn () => [
                    'capacity' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                range(1, $count)
            )
        );
    }

    public function test_availability_paginated_with_meta(): void
    {
        $this->createSlots(250);

        $first = $this->getJson('/api/slots/availability?page=1');
        $first->assertStatus(200)
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonPath('meta.total', 250)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonStructure(['data' => [['slot_id', 'capacity', 'remaining']]]);

        $last = $this->getJson('/api/slots/availability?page=3');
        $last->assertStatus(200)
            ->assertJsonCount(50, 'data')
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_availability_invalid_page_422(): void
    {
        $this->createSlots(5);

        $this->getJson('/api/slots/availability?page=0')->assertStatus(422);
        $this->getJson('/api/slots/availability?page=-1')->assertStatus(422);
        $this->getJson('/api/slots/availability?page=abc')->assertStatus(422);
    }

    public function test_availability_default_page(): void
    {
        $this->createSlots(5);

        $this->getJson('/api/slots/availability')
            ->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_availability_page_beyond_last_returns_empty(): void
    {
        $this->createSlots(250);

        $this->getJson('/api/slots/availability?page=999')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.current_page', 999)
            ->assertJsonPath('meta.total', 250);
    }
}
