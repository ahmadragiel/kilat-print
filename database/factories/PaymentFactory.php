<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Pending,
            'method' => PaymentMethod::BankTransfer->value,
            'amount' => fake()->randomFloat(2, 10000, 1000000),
            'expires_at' => now()->addHours(24),
            'proof_version' => 1,
        ];
    }
}
