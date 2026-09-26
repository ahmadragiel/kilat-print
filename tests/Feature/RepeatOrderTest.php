<?php

namespace Tests\Feature;

use App\Enums\PricingType;
use App\Services\RepeatOrderService;
use Tests\TestCase;

class RepeatOrderTest extends TestCase
{
    public function test_repeat_order_copies_configuration_and_recalculates_price_without_creating_payment_or_order(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $material = $this->attachMaterial($product, PricingType::PER_ITEM, 500);
        $finishing = $this->attachFinishing($product, PricingType::PER_ITEM, 250);
        $source = $this->makeOrder($customer);
        $this->makeOrderItem($source, $product, [
            'quantity' => 3,
            'size' => 'A3',
            'length_cm' => 30,
            'width_cm' => 42,
            'material_id' => $material->id,
            'finishing_id' => $finishing->id,
            'color' => 'Blue',
            'production_method' => 'offset',
            'custom_parameters' => ['bleed' => true],
            'notes' => 'Keep this configuration.',
            'unit_price' => 1,
            'line_total' => 1,
        ]);
        $this->makePayment($source);
        $orderCountBefore = $customer->orders()->count();
        $paymentCountBefore = $source->payment()->count();

        $response = $this->actingAs($customer->user)->post(route('customer.orders.repeat', $source));

        $response->assertRedirect(route('cart.index'));
        $item = $customer->fresh()->cart->items()->sole();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(3, $item->quantity);
        $this->assertSame('A3', $item->size);
        $this->assertSame($material->id, $item->material_id);
        $this->assertSame($finishing->id, $item->finishing_id);
        $this->assertSame('offset', $item->production_method);
        $this->assertSame(['bleed' => true], $item->custom_parameters);
        $this->assertSame((10000 + 500 + 250) * 3, (int) $item->price_at_addition);
        $this->assertSame($orderCountBefore, $customer->orders()->count());
        $this->assertSame($paymentCountBefore, $source->payment()->count());
        $this->assertDatabaseCount('orders', $orderCountBefore);
    }

    public function test_customer_cannot_repeat_another_customers_order(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($owner);
        $this->makeOrderItem($source, $product);

        $this->actingAs($other->user)
            ->post(route('customer.orders.repeat', $source))
            ->assertForbidden();
        $this->assertDatabaseCount('carts', 0);
    }

    public function test_repeat_service_recalculates_against_current_catalog_prices(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $source = $this->makeOrder($customer);
        $this->makeOrderItem($source, $product, [
            'quantity' => 2,
            'unit_price' => 1,
            'line_total' => 1,
        ]);
        $product->priceRules()->update(['price' => 12500]);

        $cart = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));

        $this->assertSame(25000, (int) $cart->items()->sole()->price_at_addition);
    }
}
