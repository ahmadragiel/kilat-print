<?php

namespace Tests\Feature;

use App\Enums\PricingType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartTest extends TestCase
{
    public function test_customer_can_add_a_configured_item_and_server_stores_the_calculated_price(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $design = UploadedFile::fake()->create('reference.pdf', 20, 'application/pdf');

        $response = $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'size' => 'A5',
            'length_cm' => 10,
            'width_cm' => 20,
            'color' => 'Blue',
            'notes' => 'Please print carefully.',
            'design_file' => $design,
            // A client-supplied price must never win over the server calculation.
            'price_at_addition' => 1,
        ]);

        $response->assertRedirect(route('cart.index'));
        $item = $customer->cart->items()->sole();
        $this->assertSame(2, $item->quantity);
        // Price comes from the product tariff, size and quantity only.
        $this->assertSame(20000, (int) $item->price_at_addition);
        $this->assertNull($item->material_id);
        $this->assertNull($item->finishing_id);
        $this->assertSame('Blue', $item->color);
        $this->assertNull($item->production_method);
        $this->assertArrayNotHasKey('production_method', $item->custom_parameters);
        $this->assertNotNull($item->design_reference);
        Storage::disk('local')->assertExists($item->design_reference);
        $this->assertSame('Please print carefully.', $item->notes);
    }

    public function test_a_stale_client_cannot_set_a_new_production_method(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'production_method' => 'sublimation',
        ])->assertRedirect(route('cart.index'));

        $item = $customer->cart->items()->sole();
        $this->assertNull($item->production_method);
        $this->assertArrayNotHasKey('production_method', $item->custom_parameters);
    }

    public function test_customer_can_update_item_configuration_and_price_is_recalculated(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_SQM, 20000);
        $cart = $this->makeCart($customer);
        $item = $this->makeCartItem($cart, $product, 1, [
            'length_cm' => 100,
            'width_cm' => 100,
            'price_at_addition' => 1,
            'production_method' => 'large_format',
        ]);

        $response = $this->actingAs($customer->user)->patch(route('cart.update', $item), [
            'quantity' => 2,
            'length_cm' => 100,
            'width_cm' => 50,
            'color' => 'Red',
        ]);

        $response->assertSessionHas('success');
        $item->refresh();
        $this->assertSame(2, $item->quantity);
        $this->assertSame(20000, (int) $item->price_at_addition);
        // The form no longer sends a method, so the historical value must survive.
        $this->assertSame('large_format', $item->production_method);
        $this->assertSame('Red', $item->color);
    }

    public function test_editing_a_cart_item_keeps_its_historical_production_method(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($customer);
        $item = $this->makeCartItem($cart, $product, 1, [
            'production_method' => 'offset',
        ]);

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
            'editing_cart_item_id' => $item->id,
        ])->assertRedirect(route('cart.index'));

        $this->assertSame(3, $item->fresh()->quantity);
        $this->assertSame('offset', $item->fresh()->production_method);
    }

    public function test_customer_can_remove_an_item_and_its_private_reference_file(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($customer);
        $path = 'design-references/'.$customer->user_id.'/reference.pdf';
        Storage::disk('local')->put($path, 'private reference');
        $item = $this->makeCartItem($cart, $product, 1, ['design_reference' => $path]);

        $this->actingAs($customer->user)
            ->delete(route('cart.destroy', $item))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_customer_cannot_update_or_remove_another_customers_cart_item(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $product = $this->makeProduct();
        $item = $this->makeCartItem($this->makeCart($owner), $product);

        $this->actingAs($other->user)
            ->patch(route('cart.update', $item), [
                'quantity' => 4,
            ])
            ->assertForbidden();
        $this->actingAs($other->user)
            ->delete(route('cart.destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('cart_items', [
            'id' => $item->id,
            'quantity' => $item->quantity,
            'cart_id' => $item->cart_id,
        ]);
    }

    public function test_cart_index_uses_the_owned_cart_and_persisted_totals(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(price: 7000);
        $item = $this->makeCartItem($this->makeCart($customer), $product, 3, [
            'price_at_addition' => 21000,
        ]);

        $response = $this->actingAs($customer->user)->get(route('cart.index'));

        $response->assertOk();
        $this->assertTrue($response->viewData('cart')->is($customer->fresh()->cart));
        $this->assertSame(21000, $response->viewData('grandTotal'));
        $this->assertTrue($response->viewData('cart')->items->contains($item));
    }
}
