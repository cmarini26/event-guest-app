<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\SubEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubEvent>
 */
class SubEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => fake()->sentence(2),
            'description' => fake()->optional()->paragraph(),
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(30)->addHour(),
            'location' => fake()->optional()->words(2, true),
            'capacity' => null,
            'sort_order' => 0,
        ];
    }
}
