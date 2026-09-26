<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Operator>
 */
class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->operator(),
            'employee_code' => 'OP-'.fake()->unique()->numerify('#####'),
            'phone' => fake()->numerify('08##########'),
            'specialization' => 'Digital printing',
            'notes' => null,
            'is_active' => true,
        ];
    }
}
