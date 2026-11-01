<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PricingType;
use App\Models\Address;
use App\Models\Customer;
use App\Support\DeliveryFee;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * The delivery tariff must come from server configuration. A missing or zero
 * value is a configuration error; free delivery only exists when explicitly
 * enabled with DELIVERY_FREE=true.
 */
class DeliveryFeeConfigurationTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_missing_delivery_fee_is_a_configuration_error(): void
    {
        config(['printing.delivery_fee' => null, 'printing.delivery_free' => false]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DELIVERY_FEE');

        DeliveryFee::amount();
    }

    public function test_zero_delivery_fee_requires_explicit_free_delivery(): void
    {
        foreach (['', '0', 0] as $value) {
            config(['printing.delivery_fee' => $value, 'printing.delivery_free' => false]);

            try {
                DeliveryFee::amount();
                $this->fail('Nilai ongkir '.var_export($value, true).' seharusnya ditolak.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('DELIVERY_FREE', $exception->getMessage());
            }
        }
    }

    public function test_explicit_free_delivery_returns_zero(): void
    {
        config(['printing.delivery_fee' => null, 'printing.delivery_free' => true]);

        $this->assertSame(0, DeliveryFee::amount());
    }

    public function test_checkout_page_returns_a_controlled_503_when_delivery_fee_is_missing(): void
    {
        config(['printing.delivery_fee' => null, 'printing.delivery_free' => false]);

        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeAddress($customer);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product);

        $response = $this->actingAs($customer->user)->get(route('checkout.index'));

        $response->assertStatus(503)
            ->assertSee('Checkout belum tersedia')
            ->assertSee('Tarif pengiriman');
    }

    public function test_checkout_store_returns_a_controlled_503_when_delivery_fee_is_missing(): void
    {
        config(['printing.delivery_fee' => null, 'printing.delivery_free' => false]);
        [$customer, $address] = $this->customerWithCart(10000, 2);

        $response = $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ]);

        $response->assertStatus(503)->assertSee('Checkout belum tersedia');
        $this->assertSame(0, $customer->orders()->count());
    }

    public function test_server_tariff_overrides_a_client_supplied_shipping_fee(): void
    {
        config(['printing.delivery_fee' => 15000, 'printing.delivery_free' => false]);
        [$customer, $address] = $this->customerWithCart(10000, 2);

        $response = $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
            'shipping_fee' => 1,
            'grand_total' => 1,
        ]);

        $order = $customer->orders()->latest('id')->firstOrFail();

        $response->assertRedirect(route('customer.orders.payment', $order));
        $this->assertSame(15000, (int) $order->shipping_fee);
        $this->assertSame(35000, (int) $order->grand_total);
    }

    public function test_explicit_free_delivery_creates_an_order_without_shipping_fee(): void
    {
        config(['printing.delivery_fee' => null, 'printing.delivery_free' => true]);
        [$customer, $address] = $this->customerWithCart(10000, 2);

        $response = $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ]);

        $order = $customer->orders()->latest('id')->firstOrFail();

        $response->assertRedirect(route('customer.orders.payment', $order));
        $this->assertSame(0, (int) $order->shipping_fee);
        $this->assertSame(20000, (int) $order->grand_total);
    }

    /** @return array{Customer, Address} */
    private function customerWithCart(int $price, int $quantity): array
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, $price);
        $address = $this->makeAddress($customer);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, $quantity);

        return [$customer, $address];
    }
}
