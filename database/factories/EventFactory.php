<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'slug' => fake()->unique()->slug(),
            'description' => fake()->paragraph(),
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(30)->addHours(3),
            'timezone' => 'America/New_York',
            'venue_name' => fake()->company(),
            'venue_address' => fake()->address(),
            'status' => 'draft',
            'max_guests' => null,
            'rsvp_deadline' => null,
            'allow_plus_ones' => true,
            'max_plus_ones_per_guest' => 1,
            'collect_dietary' => false,
            'collect_accessibility' => false,
            'collect_seating' => false,
            'require_phone' => false,
        ];
    }
}
