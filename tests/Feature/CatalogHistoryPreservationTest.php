<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\ProductionStatus;
use App\Models\Product;
use Database\Seeders\RealCatalogSeeder;
use Tests\TestCase;

/**
 * Retiring the demo catalog must never rewrite history. Old order items keep
 * their product name, slug, price, variant, design, production record, payment,
 * and pickup snapshot even after the demo product becomes inactive.
 */
class CatalogHistoryPreservationTest extends TestCase
{
    public function test_old_demo_orders_and_invoices_stay_readable_after_the_real_catalog_is_seeded(): void
    {
        $customer = $this->makeCustomer();
        $admin = $this->makeAdmin();

        $demoProduct = $this->makeProduct(PricingType::PER_ITEM, 25000, [
            'name' => 'Mug Custom',
            'slug' => 'mug-custom',
        ]);

        $order = $this->makeOrder($customer, [
            'number' => 'KP-OLD-0001',
            'status' => OrderStatus::PAYMENT_CONFIRMED,
            'subtotal' => 50000,
            'shipping_fee' => 0,
            'grand_total' => 50000,
            'shipping_method' => 'pickup',
        ]);

        $item = $this->makeOrderItem($order, $demoProduct, [
            'product_name' => 'Mug Custom',
            'product_slug' => 'mug-custom',
            'quantity' => 2,
            'size' => 'Large',
            'variant' => 'large',
            'variant_label' => 'Large',
            'color' => 'Navy',
            'unit_price' => 25000,
            'line_total' => 50000,
        ]);

        $this->makeOrderAddress($order, ['shipping_method' => 'pickup']);
        $this->makePayment($order, [
            'status' => PaymentStatus::Paid,
            'amount' => 50000,
            'paid_at' => now()->subDay(),
        ]);
        $this->makeProduction($order, ['status' => ProductionStatus::IN_DESIGN]);
        $this->makeDesign($order, ['order_item_id' => $item->id]);

        $this->seed(RealCatalogSeeder::class);

        $demoProduct->refresh();

        $this->assertSame('inactive', $demoProduct->status);
        $this->assertFalse($demoProduct->is_featured);
        $this->assertSame(0, $demoProduct->priceRules()->where('active', true)->count());
        $this->assertNotNull(Product::withTrashed()->find($demoProduct->id));

        $item->refresh();

        $this->assertSame('Mug Custom', $item->product_name);
        $this->assertSame('mug-custom', $item->product_slug);
        $this->assertSame('Large', $item->variant_label);
        $this->assertSame(25000, (int) $item->unit_price);
        $this->assertSame(50000, (int) $item->line_total);

        $order->refresh();

        $this->assertSame(OrderStatus::PAYMENT_CONFIRMED, $order->status);
        $this->assertSame(50000, (int) $order->subtotal);
        $this->assertSame(50000, (int) $order->grand_total);
        $this->assertSame('pickup', $order->shipping_method);

        $this->assertDatabaseHas('order_items', [
            'id' => $item->id,
            'product_name' => 'Mug Custom',
            'product_slug' => 'mug-custom',
            'line_total' => 50000,
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Paid->value,
        ]);
        $this->assertDatabaseHas('production_orders', ['order_id' => $order->id]);
        $this->assertDatabaseHas('design_files', ['order_id' => $order->id]);

        $this->actingAs($customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Mug Custom')
            ->assertSee('Large')
            ->assertSee('50.000');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Mug Custom');

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $order))
            ->assertOk()
            ->assertSee('Mug Custom')
            ->assertSee('Large')
            ->assertSee('50.000');
    }

    public function test_old_pickup_order_remains_readable_even_though_new_checkout_requires_delivery(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 15000, [
            'name' => 'Kertas Kado Custom',
            'slug' => 'kertas-kado-custom',
        ]);

        $order = $this->makeOrder($customer, [
            'number' => 'KP-OLD-PICKUP',
            'status' => OrderStatus::COMPLETED,
            'shipping_method' => 'pickup',
            'subtotal' => 15000,
            'grand_total' => 15000,
        ]);
        $this->makeOrderItem($order, $product, [
            'product_name' => 'Kertas Kado Custom',
            'product_slug' => 'kertas-kado-custom',
            'unit_price' => 15000,
            'line_total' => 15000,
        ]);
        $this->makeOrderAddress($order, ['shipping_method' => 'pickup']);

        $this->actingAs($customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Kertas Kado Custom')
            ->assertSee('15.000');
    }
}
