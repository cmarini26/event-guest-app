<?php

namespace Database\Factories;

use App\Models\CustomDomain;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomDomain>
 */
class CustomDomainFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'domain' => fake()->unique()->domainName(),
            'verification_token' => Str::random(40),
            'verified_at' => null,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['verified_at' => now()]);
    }
}
