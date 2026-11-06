<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Services\PriceCalculationService;
use Database\Seeders\RealCatalogSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Variant selection must be validated on the server and snapshotted into the
 * cart and order. Prices always come from the active price rules, never from a
 * value supplied by the browser.
 */
class CatalogVariationPricingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RealCatalogSeeder::class);
    }

    public function test_paper_bag_prices_are_resolved_from_variant_rules(): void
    {
        $product = Product::where('slug', 'paper-bag-custom')->firstOrFail();
        $calculator = app(PriceCalculationService::class);

        $expected = [
            'small' => 19500,
            'medium' => 24500,
            'large' => 32000,
            'large-x2' => 39500,
        ];

        foreach ($expected as $variant => $price) {
            $result = $calculator->calculate($product, [
                'quantity' => 1,
                'variant' => $variant,
            ]);

            $this->assertSame($price, $result['total'], "Harga varian {$variant} tidak sesuai.");
            $this->assertSame($variant, $result['variant']);
            $this->assertSame($product->variantLabel($variant), $result['variant_label']);
        }
    }

    public function test_paper_bag_requires_a_valid_variant(): void
    {
        $product = Product::where('slug', 'paper-bag-custom')->firstOrFail();
        $calculator = app(PriceCalculationService::class);

        try {
            $calculator->calculate($product, ['quantity' => 1]);
            $this->fail('Varian kosong seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variant', $exception->errors());
        }

        try {
            $calculator->calculate($product, ['quantity' => 1, 'variant' => 'ukuran-aneh']);
            $this->fail('Varian yang tidak terdaftar seharusnya ditolak.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('variant', $exception->errors());
        }
    }

    public function test_topi_models_are_validated_and_priced_per_unit(): void
    {
        $product = Product::where('slug', 'topi-ultah-custom')->firstOrFail();
        $calculator = app(PriceCalculationService::class);

        foreach (['love', 'polkadot', 'muka-polos', 'bintang'] as $variant) {
            $result = $calculator->calculate($product, [
                'quantity' => 3,
                'variant' => $variant,
            ]);

            $this->assertSame(38700, $result['total']);
            $this->assertSame(12900, $result['unit_price']);
            $this->assertSame($variant, $result['variant']);
            $this->assertSame($product->variantLabel($variant), $result['variant_label']);
        }

        $this->expectException(ValidationException::class);
        $calculator->calculate($product, ['quantity' => 1, 'variant' => 'model-aneh']);
    }

    public function test_inactive_wholesale_price_is_not_used_for_regular_orders(): void
    {
        $product = Product::where('slug', 'topi-ultah-custom')->firstOrFail();
        $calculator = app(PriceCalculationService::class);

        $result = $calculator->calculate($product, [
            'quantity' => 10,
            'variant' => 'love',
        ]);

        $this->assertSame(129000, $result['total']);
        $this->assertNotSame(109000, $result['total']);
    }

    public function test_price_endpoint_returns_the_server_resolved_variant(): void
    {
        $customer = $this->makeCustomer();
        $product = Product::where('slug', 'paper-bag-custom')->firstOrFail();

        $response = $this->actingAs($customer->user)->getJson(route('products.price', [
            'product' => $product,
            'quantity' => 2,
            'variant' => 'large',
        ]));

        $response->assertOk()
            ->assertJsonPath('quantity', 2)
            ->assertJsonPath('variant', 'large')
            ->assertJsonPath('variant_label', 'Large')
            ->assertJsonPath('unit_price', 32000)
            ->assertJsonPath('total', 64000);
    }

    public function test_price_endpoint_rejects_missing_or_unknown_variants(): void
    {
        $customer = $this->makeCustomer();
        $product = Product::where('slug', 'paper-bag-custom')->firstOrFail();

        $this->actingAs($customer->user)->getJson(route('products.price', [
            'product' => $product,
            'quantity' => 1,
        ]))->assertStatus(422)->assertJsonValidationErrors('variant');

        $this->actingAs($customer->user)->getJson(route('products.price', [
            'product' => $product,
            'quantity' => 1,
            'variant' => 'ukuran-aneh',
        ]))->assertStatus(422)->assertJsonValidationErrors('variant');
    }

    public function test_cart_and_order_snapshot_the_server_validated_variant(): void
    {
        Notification::fake();
        (new FakeMidtransApi)->install();
        config(['printing.delivery_fee' => 15000, 'printing.delivery_free' => false]);

        $customer = $this->makeCustomer();
        $product = Product::where('slug', 'paper-bag-custom')->firstOrFail();

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'variant' => 'large',
        ])->assertRedirect(route('cart.index'));

        $cartItem = $customer->cart->items()->sole();

        $this->assertSame('large', $cartItem->variant);
        $this->assertSame('Large', $cartItem->variant_label);
        $this->assertNull($cartItem->production_method);
        $this->assertSame(64000, (int) $cartItem->price_at_addition);

        $address = $this->makeAddress($customer);

        $this->actingAs($customer->user)->post(route('checkout.store'), [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $address->id,
            'payment_method' => PaymentMethod::Qris->value,
        ])->assertRedirect();

        $order = $customer->orders()->latest('id')->firstOrFail();
        $item = $order->items()->sole();

        $this->assertSame('large', $item->variant);
        $this->assertSame('Large', $item->variant_label);
        $this->assertSame(32000, (int) $item->unit_price);
        $this->assertSame(64000, (int) $item->line_total);
        $this->assertSame(64000, (int) $order->subtotal);
        $this->assertSame(15000, (int) $order->shipping_fee);
        $this->assertSame(79000, (int) $order->grand_total);
    }

    public function test_cart_rejects_a_missing_variant_without_creating_an_item(): void
    {
        $customer = $this->makeCustomer();
        $product = Product::where('slug', 'paper-bag-custom')->firstOrFail();

        $response = $this->actingAs($customer->user)
            ->from(route('products.show', $product))
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ]);

        $response->assertRedirect(route('products.show', $product));
        $response->assertSessionHasErrors('variant');
        $this->assertSame(0, $customer->cart?->items()->count() ?? 0);
    }
}
