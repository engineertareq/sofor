<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'traveller_'.Str::lower(Str::random(12)),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+8801'.fake()->unique()->numerify('#########'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'user',
            'is_active' => true,
            'timezone' => 'Asia/Dhaka',
            'last_seen_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    /** Neither the email nor the phone confirmed yet, like a brand new sign up. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'phone_verified_at' => null,
        ]);
    }
}
