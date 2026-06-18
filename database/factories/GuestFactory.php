<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'rsvp_token' => Str::uuid()->toString(),
            'rsvp_status' => 'pending',
            'responded_at' => null,
            'notes' => null,
            'dietary_preference' => null,
            'accessibility_needs' => null,
            'seating_preference' => null,
            'invited_at' => null,
        ];
    }
}
