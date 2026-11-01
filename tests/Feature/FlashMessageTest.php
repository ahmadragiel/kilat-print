<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PricingType;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Warning and error flashes must be visible, not just stored in the session.
 * The layouts used to render only success/status/error styling and silently
 * dropped the warning and error messages themselves.
 */
class FlashMessageTest extends TestCase
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

    public function test_customer_warning_flash_is_rendered(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)
            ->withSession(['warning' => 'Peringatan pelanggan uji.'])
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->assertSee('Peringatan pelanggan uji.');
    }

    public function test_admin_warning_and_error_flashes_are_rendered(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['warning' => 'Peringatan admin uji.'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Peringatan admin uji.');

        $this->actingAs($admin)
            ->withSession(['error' => 'Kesalahan admin uji.'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Kesalahan admin uji.');
    }

    public function test_operator_warning_flash_is_rendered(): void
    {
        $operator = $this->makeOperator();

        $this->actingAs($operator->user)
            ->withSession(['warning' => 'Peringatan operator uji.'])
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Peringatan operator uji.');
    }

    public function test_checkout_gateway_failure_warning_is_visible_on_the_order_page(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 20000);
        $address = $this->makeAddress($customer);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, 1);
        $this->midtrans->failCharge = true;

        $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();

        $this->actingAs($customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertSee('Pesanan dibuat, tetapi transaksi pembayaran belum dapat dibuat.');
    }
}
