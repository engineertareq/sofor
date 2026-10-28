<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => Str::title(fake()->unique()->words(3, true)),
            'destination' => fake()->city(),
            'description' => null,
            'cover' => 'aurora',
            'emoji' => 'compass',
            'currency' => 'BDT',
            'budget' => null,
            'status' => 'active',
            'invite_code' => Trip::freshInviteCode(),
            'starts_on' => null,
            'ends_on' => null,
            'ended_at' => null,
            'last_activity_at' => now(),
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => 'ended',
            'ended_at' => now(),
        ]);
    }
}
