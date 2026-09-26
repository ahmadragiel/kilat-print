<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class RepeatOrderService
{
    public function __construct(private readonly PriceCalculationService $prices) {}

    public function repeat(Customer $customer, Order $source): Cart
    {
        abort_unless($source->customer_id === $customer->id, 403);
        abort_if($source->items->isEmpty(), 422, 'Konfigurasi order ini tidak dapat diulang.');

        return DB::transaction(function () use ($customer, $source) {
            $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);
            $cart->items()->delete();

            foreach ($source->items as $item) {
                $price = $this->prices->calculate($item->product, [
                    'quantity' => $item->quantity,
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                    'material_id' => $item->material_id,
                    'finishing_id' => $item->finishing_id,
                ]);
                $cart->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'size' => $item->size,
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                    'material_id' => $item->material_id,
                    'finishing_id' => $item->finishing_id,
                    'color' => $item->color,
                    'production_method' => $item->production_method,
                    'custom_parameters' => $item->custom_parameters,
                    'custom_design_draft_id' => $item->custom_design_draft_id,
                    'price_at_addition' => $price['total'],
                    'notes' => $item->notes,
                ]);
            }

            return $cart->load('items');
        });
    }
}
