<?php

namespace Database\Factories;

use App\Enums\DesignStatus;
use App\Models\DesignFile;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DesignFile>
 */
class DesignFileFactory extends Factory
{
    protected $model = DesignFile::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'order_item_id' => null,
            'original_filename' => 'design.pdf',
            'stored_filename' => 'design.pdf',
            'path' => 'designs/demo/'.fake()->uuid().'.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(1000, 5000000),
            'version' => 1,
            'status' => DesignStatus::PENDING,
            'uploaded_at' => now(),
        ];
    }
}
