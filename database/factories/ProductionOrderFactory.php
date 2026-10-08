<?php

namespace Database\Factories;

use App\Enums\ProductionStatus;
use App\Models\Order;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'operator_id' => null,
            'status' => ProductionStatus::IN_DESIGN,
            'progress' => 0,
            'notes' => null,
            'deadline' => now()->addDays(5),
        ];
    }
}
