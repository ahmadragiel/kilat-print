<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\ProductionStatus;
use App\Models\Address;
use App\Models\Customer;
use App\Services\OrderService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Regressions for removing pickup from new orders while keeping legacy pickup
 * orders readable and the delivery-only operational flow intact.
 */
class PickupRemovalRegressionTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['printing.delivery_fee' => 15000]);
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_valid_delivery_checkout_snapshots_the_owned_address_and_server_totals(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, 2);

        $address = $this->makeAddress($customer, [
            'label' => 'Kantor',
            'recipient' => 'Siti Penerima',
            'phone' => '081277788899',
            'address' => 'Jalan Melati No. 7',
            'address_line2' => 'Lantai 3',
            'district' => 'Wonokromo',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'postal_code' => '60243',
            'country' => 'Indonesia',
        ]);

        $response = $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->deliveryPayload($customer, $address));

        $order = $customer->orders()->latest('id')->firstOrFail();

        $response->assertRedirect(route('customer.orders.payment', $order));
        $this->assertSame('delivery', $order->shipping_method);
        $this->assertSame(20000, (int) $order->subtotal);
        $this->assertSame(15000, (int) $order->shipping_fee);
        $this->assertSame(35000, (int) $order->grand_total);
        $this->assertSame(35000, (int) $order->payment->amount);

        $snapshot = $order->address()->firstOrFail();
        $this->assertSame('Kantor', $snapshot->label);
        $this->assertSame('Siti Penerima', $snapshot->recipient);
        $this->assertSame('081277788899', $snapshot->phone);
        $this->assertSame('Jalan Melati No. 7', $snapshot->address);
        $this->assertSame('Lantai 3', $snapshot->address_line2);
        $this->assertSame('Wonokromo', $snapshot->district);
        $this->assertSame('Surabaya', $snapshot->city);
        $this->assertSame('Jawa Timur', $snapshot->province);
        $this->assertSame('60243', $snapshot->postal_code);
        $this->assertSame('delivery', $snapshot->shipping_method);
    }

    public function test_checkout_page_only_offers_saved_address_delivery(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $this->makeCartItem($this->makeCart($customer), $product, 2);
        $address = $this->makeAddress($customer);

        $response = $this->actingAs($customer->user)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertViewHas('defaultShippingMethod', 'delivery');
        $response->assertViewHas('shippingCost', 15000);
        $response->assertViewHas('grandTotal', 35000);
        $response->assertSee('name="address_id" value="'.$address->id.'"', false);
        $response->assertSee('name="shipping_method" value="delivery"', false);

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        $addressInput = (new \DOMXPath($dom))
            ->query('//input[@name="address_id" and @value="'.$address->id.'"]')
            ->item(0);
        $this->assertNotNull($addressInput);
        $this->assertTrue($addressInput->hasAttribute('checked'));
        libxml_clear_errors();
        $response->assertDontSee('name="shipping_method" value="pickup"', false);
        $response->assertDontSee('Ambil di lokasi');
        $response->assertSee('Kirim ke alamat');
    }

    public function test_customer_without_an_address_cannot_create_an_order(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);

        $page = $this->actingAs($customer->user)->get(route('checkout.index'));

        $page->assertOk();
        $page->assertViewHas('defaultShippingMethod', 'delivery');
        $page->assertSee('Belum ada alamat pengiriman');
        $page->assertDontSee('Ambil di lokasi');

        $response = $this->actingAs($customer->user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), [
                'name' => $customer->user->name,
                'phone' => '081200000001',
                'email' => $customer->user->email,
                'shipping_method' => 'delivery',
                'payment_method' => PaymentMethod::Qris->value,
            ]);

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_customer_cannot_use_another_customers_address(): void
    {
        $customer = $this->makeCustomer();
        $otherCustomer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);
        $otherAddress = $this->makeAddress($otherCustomer);

        $response = $this->actingAs($customer->user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->deliveryPayload($customer, $otherAddress));

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHasErrors('address_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->items()->count());
        $this->assertDatabaseHas('addresses', ['id' => $otherAddress->id, 'customer_id' => $otherCustomer->id]);
    }

    public function test_direct_pickup_requests_are_rejected_without_fallback(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);
        $address = $this->makeAddress($customer);

        $response = $this->actingAs($customer->user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->deliveryPayload($customer, $address, [
                'shipping_method' => 'pickup',
            ]));

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHasErrors('shipping_method');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->items()->count());

        $payload = $this->deliveryPayload($customer, $address);
        unset($payload['shipping_method']);

        $this->actingAs($customer->user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $payload)
            ->assertSessionHasErrors('shipping_method');

        $this->assertDatabaseCount('orders', 0);

        try {
            app(OrderService::class)->checkout($customer, $this->deliveryPayload($customer, $address, [
                'shipping_method' => 'pickup',
            ]));
            $this->fail('OrderService accepted a pickup request for a new order.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shipping_method', $exception->errors());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_unknown_or_empty_shipping_methods_are_rejected(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);
        $address = $this->makeAddress($customer);

        foreach (['courier', ''] as $method) {
            $this->actingAs($customer->user)
                ->from(route('checkout.index'))
                ->post(route('checkout.store'), $this->deliveryPayload($customer, $address, [
                    'shipping_method' => $method,
                ]))
                ->assertSessionHasErrors('shipping_method');

            $this->assertDatabaseCount('orders', 0);
        }

        $this->assertSame(1, $cart->items()->count());
    }

    public function test_client_supplied_address_and_totals_do_not_override_the_server_calculation(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);
        $address = $this->makeAddress($customer, [
            'recipient' => 'Penerima Asli',
            'address' => 'Jalan Asli No. 1',
            'city' => 'Surabaya',
        ]);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->deliveryPayload($customer, $address, [
                'recipient' => 'Penerima Palsu',
                'address' => 'Jalan Palsu No. 99',
                'city' => 'Kota Palsu',
                'subtotal' => 1,
                'shipping_fee' => 0,
                'shipping_cost' => 0,
                'delivery_fee' => 0,
                'grand_total' => 1,
            ]))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $snapshot = $order->address()->firstOrFail();

        $this->assertSame(10000, (int) $order->subtotal);
        $this->assertSame(15000, (int) $order->shipping_fee);
        $this->assertSame(25000, (int) $order->grand_total);
        $this->assertSame(25000, (int) $order->payment->amount);
        $this->assertSame('Penerima Asli', $snapshot->recipient);
        $this->assertSame('Jalan Asli No. 1', $snapshot->address);
        $this->assertSame('Surabaya', $snapshot->city);
    }

    public function test_checkout_preserves_the_selected_address_and_notes_after_a_validation_error(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);
        $address = $this->makeAddress($customer);

        $response = $this->actingAs($customer->user)
            ->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->deliveryPayload($customer, $address, [
                'shipping_notes' => str_repeat('a', 501),
            ]));

        $response->assertRedirect(route('checkout.index'));
        $response->assertSessionHasErrors('shipping_notes');
        $response->assertSessionHasInput('address_id', $address->id);
        $response->assertSessionHasInput('shipping_method', 'delivery');
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_legacy_pickup_order_remains_readable_with_its_original_snapshot(): void
    {
        $customer = $this->makeCustomer();
        $admin = $this->makeAdmin();
        $product = $this->makeProduct();

        $order = $this->makeOrder($customer, [
            'number' => 'KP-20250101-0001',
            'shipping_method' => 'pickup',
            'status' => OrderStatus::PaymentConfirmed,
            'subtotal' => 10000,
            'shipping_fee' => 0,
            'grand_total' => 10000,
            'paid_at' => now(),
        ]);
        $this->makeOrderItem($order, $product, ['unit_price' => 10000, 'line_total' => 10000]);
        $this->makeOrderAddress($order, [
            'shipping_method' => 'pickup',
            'recipient' => 'Penerima Lama',
            'address' => 'Jalan Lama No. 5',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40111',
        ]);
        $this->makePayment($order, ['status' => PaymentStatus::Paid, 'amount' => 10000]);

        $this->actingAs($customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Ambil di lokasi')
            ->assertSee('Pesanan lama ini menggunakan metode ambil di lokasi')
            ->assertSee('Jalan Lama No. 5');

        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Metode pengiriman')
            ->assertSee('Ambil di lokasi (pesanan lama)')
            ->assertSee('Jalan Lama No. 5');

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $order))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Ambil di lokasi (pesanan lama)')
            ->assertSee('Ongkos kirim');

        $this->get(route('tracking.index', ['number' => $order->number]))
            ->assertOk()
            ->assertSee($order->number);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'shipping_method' => 'pickup',
            'shipping_fee' => 0,
            'grand_total' => 10000,
        ]);
        $this->assertDatabaseHas('order_addresses', [
            'order_id' => $order->id,
            'shipping_method' => 'pickup',
            'address' => 'Jalan Lama No. 5',
        ]);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'status' => PaymentStatus::Paid->value,
            'amount' => 10000,
        ]);
    }

    public function test_new_delivery_order_moves_through_admin_operator_and_quality_control(): void
    {
        $admin = $this->makeAdmin();
        $operator = $this->makeOperator();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 20000);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);
        $address = $this->makeAddress($customer);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->deliveryPayload($customer, $address))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $this->midtrans->settle();
        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment-refresh', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentConfirmed, $order->status);
        $production = $order->production()->firstOrFail();
        $this->assertSame(ProductionStatus::IN_DESIGN, $production->status);

        $legacy = $this->makeOrder($customer, [
            'number' => 'KP-20250101-0002',
            'shipping_method' => 'pickup',
            'status' => OrderStatus::Completed,
            'subtotal' => 10000,
            'shipping_fee' => 0,
            'grand_total' => 10000,
            'completed_at' => now(),
        ]);
        $this->makeOrderItem($legacy, $product);
        $this->makeOrderAddress($legacy, ['shipping_method' => 'pickup']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['search' => $order->number]))
            ->assertOk()
            ->assertSee($order->number);
        $this->actingAs($admin)
            ->get(route('admin.production.index'))
            ->assertOk()
            ->assertSee($order->number);
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Kirim ke alamat');

        $this->actingAs($admin)
            ->post(route('admin.production.assign', $production), [
                'operator_id' => $operator->id,
                'deadline' => now()->addDays(2)->toDateString(),
            ])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->status);

        $this->actingAs($operator->user)->get(route('operator.dashboard'))->assertOk();
        $this->actingAs($operator->user)
            ->get(route('operator.jobs.index'))
            ->assertOk()
            ->assertSee($order->number);
        $this->actingAs($operator->user)
            ->get(route('operator.jobs.show', $production))
            ->assertOk()
            ->assertSee($order->number);

        foreach (['PRINTING', 'FINISHING', 'PACKING', 'QUALITY_CONTROL'] as $status) {
            $this->actingAs($operator->user)
                ->post(route('operator.jobs.status', $production), ['status' => $status])
                ->assertSessionHas('success');
        }

        $this->actingAs($operator->user)
            ->post(route('operator.jobs.quality-check', $production), [
                'result' => 'PASS',
                'notes' => 'Hasil cetak sesuai.',
            ])
            ->assertSessionHas('success');

        $this->assertSame(ProductionStatus::COMPLETED, $production->fresh()->status);
        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);

        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee($order->number);
        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $order))
            ->assertOk()
            ->assertSee('Kirim ke alamat');
        $this->actingAs($admin)
            ->get(route('admin.orders.index', ['status' => OrderStatus::Completed->value]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee($legacy->number);
    }

    /** @param array<string, mixed> $overrides */
    private function deliveryPayload(Customer $customer, Address $address, array $overrides = []): array
    {
        return array_merge([
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ], $overrides);
    }
}
