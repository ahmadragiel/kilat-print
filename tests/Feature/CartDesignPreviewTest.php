<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CartDesignPreviewTest extends TestCase
{
    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function test_owner_can_preview_the_private_final_design(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($customer);
        $path = 'design-references/'.$customer->id.'/final-preview.png';
        $bytes = $this->pngBytes();
        Storage::disk('local')->put($path, $bytes);

        $item = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => $path,
            'custom_design_draft_id' => null,
        ]);

        $response = $this->actingAs($customer->user)
            ->get(route('cart.design-preview', $item));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertSame($bytes, $response->streamedContent());
        $response->assertDontSee($path, false);
    }

    public function test_another_customer_cannot_read_someone_elses_preview(): void
    {
        Storage::fake('local');
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($owner);
        $path = 'design-references/'.$owner->id.'/final-preview.png';
        Storage::disk('local')->put($path, $this->pngBytes());

        $item = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => $path,
            'custom_design_draft_id' => null,
        ]);

        $this->actingAs($intruder->user)
            ->get(route('cart.design-preview', $item))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($customer);
        $path = 'design-references/'.$customer->id.'/final-preview.png';
        Storage::disk('local')->put($path, $this->pngBytes());

        $item = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => $path,
            'custom_design_draft_id' => null,
        ]);

        $this->get(route('cart.design-preview', $item))
            ->assertRedirect(route('login'));
    }

    public function test_missing_private_file_returns_not_found(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($customer);

        $item = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => 'design-references/'.$customer->id.'/missing.png',
            'custom_design_draft_id' => null,
        ]);

        $this->actingAs($customer->user)
            ->get(route('cart.design-preview', $item))
            ->assertNotFound();
    }

    public function test_cart_renders_the_authorised_final_design_preview(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(attributes: ['name' => 'Kertas Kado Custom']);
        $cart = $this->makeCart($customer);
        $path = 'design-references/'.$customer->id.'/final-preview.png';
        Storage::disk('local')->put($path, $this->pngBytes());

        $item = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => $path,
            'custom_design_draft_id' => null,
        ]);

        $this->actingAs($customer->user)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee(route('cart.design-preview', $item), false)
            ->assertSee('Pratinjau desain final Kertas Kado Custom')
            ->assertSee('Lihat desain final')
            ->assertDontSee($path, false);
    }

    public function test_cart_falls_back_to_the_catalog_image_for_non_image_references(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(attributes: ['name' => 'Kertas Kado Custom']);
        $cart = $this->makeCart($customer);
        $path = 'design-references/'.$customer->id.'/brief.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test');

        $item = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => $path,
            'custom_design_draft_id' => null,
        ]);

        $this->actingAs($customer->user)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee(route('cart.design-preview', $item), false)
            ->assertSee('Lihat desain final')
            ->assertDontSee('Pratinjau desain final Kertas Kado Custom');
    }
}
