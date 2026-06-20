<?php

namespace Database\Factories;

use App\Models\Guest;
use App\Models\PlusOne;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlusOne>
 */
class PlusOneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'guest_id' => Guest::factory(),
            'name' => fake()->name(),
            'dietary_preference' => null,
        ];
    }
}
