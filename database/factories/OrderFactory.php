<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50000, 1000000);

        return [
            'number' => 'KP-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => $subtotal,
            'shipping_fee' => 0,
            'grand_total' => $subtotal,
            'shipping_method' => fake()->randomElement(['pickup', 'delivery']),
            'internal_notes' => null,
            'deadline' => now()->addDays(7),
            'paid_at' => null,
            'completed_at' => null,
        ];
    }

    public function status(OrderStatus|string $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::PAYMENT_CONFIRMED,
            'paid_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::COMPLETED,
            'paid_at' => now()->subDays(2),
            'completed_at' => now(),
        ]);
    }
}
