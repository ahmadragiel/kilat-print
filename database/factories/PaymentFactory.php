<?php

namespace Database\Factories;

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
            'status' => PaymentStatus::UNPAID,
            'method' => 'BANK_TRANSFER',
            'amount' => fake()->randomFloat(2, 10000, 1000000),
            'bank_name' => 'Bank Demo',
            'account_number' => '1234567890',
            'account_name' => 'PT Solusi Print Cepat',
            'proof_version' => 1,
        ];
    }
}
