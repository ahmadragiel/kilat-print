<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::CUSTOMER,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::ADMIN]);
    }

    public function operator(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::OPERATOR]);
    }

    public function customer(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::CUSTOMER]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }
}
