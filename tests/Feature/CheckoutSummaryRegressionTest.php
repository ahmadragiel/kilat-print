<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PricingType;
use App\Models\Customer;
use Illuminate\Support\Facades\Notification;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Regressions for the checkout summary and the address snapshot.
 *
 * The server recalculates every line, but the Blade summary used to render the
 * stale cart price and a null grand total.
 */
class CheckoutSummaryRegressionTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_checkout_uses_recalculated_line_totals_and_a_grand_total(): void
    {
        config(['printing.delivery_fee' => 15000]);

        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $cart = $this->makeCart($customer);
        $item = $this->makeCartItem($cart, $product, 2, ['price_at_addition' => 1]);
        $this->makeAddress($customer);

        $response = $this->actingAs($customer->user)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertViewHas('subtotal', 20000);
        $response->assertViewHas('grandTotal', 35000);
        $response->assertViewHas('defaultShippingMethod', 'delivery');
        $response->assertViewHas('lineTotals', function ($lineTotals) use ($item) {
            return (int) data_get($lineTotals, $item->id) === 20000;
        });

        $response->assertSee('Rp 35.000');
        $response->assertDontSee('Belum tersedia');
        $response->assertDontSee('Ambil di lokasi');
        $response->assertSee('Kirim ke alamat');
    }

    public function test_checkout_keeps_delivery_as_the_only_method_when_the_customer_has_no_address(): void
    {
        config(['printing.delivery_fee' => 15000]);

        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $this->makeCartItem($this->makeCart($customer), $product, 2, ['price_at_addition' => 1]);

        $response = $this->actingAs($customer->user)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertViewHas('grandTotal', 35000);
        $response->assertViewHas('defaultShippingMethod', 'delivery');
        $response->assertSee('Belum ada alamat pengiriman');
        $response->assertDontSee('Ambil di lokasi');
    }

    public function test_checkout_renders_address_snapshot_values_without_javascript(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeCartItem($this->makeCart($customer), $product);
        $address = $this->makeAddress($customer, [
            'recipient' => 'Budi Penerima',
            'address' => 'Jalan Mawar No. 5',
            'address_line2' => 'Blok B',
        ]);

        $response = $this->actingAs($customer->user)->get(route('checkout.index'));

        $response->assertOk();
        $response->assertSee('name="address_id" value="'.$address->id.'"', false);
        $response->assertSee('Budi Penerima');
        $response->assertSee('Jalan Mawar No. 5');
        $response->assertSee('Blok B');
    }

    public function test_checkout_persists_shipping_notes_and_address_line2(): void
    {
        $customer = $this->makeCustomer();
        $address = $this->makeAddress($customer, ['address_line2' => 'Blok B No. 7']);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 1);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer, [
                'address_id' => $address->id,
                'shipping_notes' => 'Titip di pos keamanan.',
            ]))
            ->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $snapshot = $order->address()->firstOrFail();

        $this->assertSame('Blok B No. 7', $snapshot->address_line2);
        $this->assertSame('Titip di pos keamanan.', $snapshot->notes);
    }

    public function test_checkout_rejects_shipping_notes_longer_than_the_limit(): void
    {
        $customer = $this->makeCustomer();
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $this->makeProduct(PricingType::PER_ITEM, 20000), 1);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer, [
                'shipping_notes' => str_repeat('a', 501),
            ]))
            ->assertSessionHasErrors('shipping_notes');

        $this->assertDatabaseCount('orders', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function checkoutPayload(Customer $customer, array $overrides = []): array
    {
        $address = $customer->addresses()->orderByDesc('is_primary')->first()
            ?? $this->makeAddress($customer);

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
