<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->customer(),
            'customer_code' => 'CUST-'.fake()->unique()->numerify('#####'),
            'phone' => fake()->numerify('08##########'),
            'company' => fake()->optional()->company(),
            'notes' => null,
            'is_active' => true,
        ];
    }
}
