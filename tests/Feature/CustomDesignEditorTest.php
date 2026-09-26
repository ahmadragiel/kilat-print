<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CustomDesignAsset;
use App\Models\CustomDesignDraft;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomDesignEditorTest extends TestCase
{
    protected function designPayload(array $overrides = []): array
    {
        return array_replace([
            'front' => ['elements' => []],
            'back' => ['elements' => []],
        ], $overrides);
    }

    protected function textElement(array $overrides = []): array
    {
        return array_replace([
            'id' => 'el_text_1',
            'type' => 'text',
            'text' => 'Promo Kilat Print',
            'x' => 100.0,
            'y' => 80.0,
            'width' => 200.0,
            'height' => 60.0,
            'rotation' => 15.0,
            'scaleX' => 1.0,
            'scaleY' => 1.0,
            'opacity' => 0.9,
            'visible' => true,
            'side' => 'front',
            'layer' => 0,
            'font_size' => 32.0,
            'font_family' => 'Arial',
            'font_weight' => 'bold',
            'font_style' => 'normal',
            'text_align' => 'center',
            'fill' => '#111827',
        ], $overrides);
    }

    protected function stickerElement(array $overrides = []): array
    {
        return array_replace([
            'id' => 'el_sticker_1',
            'type' => 'sticker',
            'sticker_key' => 'star',
            'src' => '/images/stickers/star.svg',
            'x' => 300.0,
            'y' => 200.0,
            'width' => 120.0,
            'height' => 120.0,
            'rotation' => 0.0,
            'scaleX' => 1.0,
            'scaleY' => 1.0,
            'opacity' => 1.0,
            'visible' => true,
            'side' => 'back',
            'layer' => 0,
        ], $overrides);
    }

    /**
     * A genuine 1x1 PNG on disk so the service's getimagesize() and MIME sniffing
     * both work. UploadedFile::fake()->image() requires the GD extension, which is
     * optional on many PHP installs, so we craft the bytes directly instead.
     */
    protected function fakePng(string $name = 'design.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $path = tempnam(sys_get_temp_dir(), 'kilat-design-').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    // ---------------------------------------------------------------------
    // Persistence + authorization
    // ---------------------------------------------------------------------

    public function test_customer_can_create_a_design_draft_with_front_and_back_elements(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $response = $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'specification' => [
                'quantity' => 2,
                'size' => 'A4',
                'length_cm' => 30.0,
                'width_cm' => 21.0,
                'production_method' => 'digital',
            ],
            'design' => $this->designPayload([
                'front' => ['elements' => [$this->textElement()]],
                'back' => ['elements' => [$this->stickerElement()]],
            ]),
        ]);

        $response->assertOk()->assertJsonStructure(['id', 'version', 'design', 'asset_urls']);
        $this->assertSame(1, $response->json('version'));

        $draft = CustomDesignDraft::firstOrFail();
        $this->assertSame($customer->id, $draft->customer_id);
        $this->assertSame($product->id, $draft->product_id);
        $this->assertSame('draft', $draft->status);
        $this->assertCount(1, $draft->elements('front'));
        $this->assertCount(1, $draft->elements('back'));
        $this->assertSame('Promo Kilat Print', $draft->elements('front')[0]['text']);
        $this->assertSame(2, $draft->specification['quantity']);
    }

    public function test_saving_a_draft_increments_its_version_and_persists_new_state(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($customer, $product);

        $response = $this->actingAs($customer->user)->putJson(route('custom-designs.update', $draft), [
            'product_id' => $product->id,
            'specification' => ['quantity' => 5],
            'design' => $this->designPayload([
                'front' => ['elements' => [$this->textElement(['text' => 'Versi baru'])]],
            ]),
        ]);

        $response->assertOk();
        $this->assertSame(2, $response->json('version'));
        $this->assertSame('Versi baru', $draft->fresh()->elements('front')[0]['text']);
    }

    public function test_guest_and_non_customer_cannot_create_a_draft(): void
    {
        $product = $this->makeProduct();

        $this->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => $this->designPayload(),
        ])->assertUnauthorized();

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => $this->designPayload(),
        ])->assertForbidden();
    }

    public function test_customer_cannot_update_another_customers_draft(): void
    {
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($owner, $product);

        $this->actingAs($intruder->user)->putJson(route('custom-designs.update', $draft), [
            'product_id' => $product->id,
            'design' => $this->designPayload(),
        ])->assertForbidden();
    }

    public function test_sticker_source_must_be_an_approved_local_asset(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        // A remote sticker is rejected.
        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => $this->designPayload([
                'front' => ['elements' => [$this->stickerElement(['src' => 'https://evil.example.com/star.svg'])]],
            ]),
        ])->assertStatus(422);

        // A local sticker outside the approved directory is rejected.
        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => $this->designPayload([
                'front' => ['elements' => [$this->stickerElement(['src' => '/images/other/star.svg'])]],
            ]),
        ])->assertStatus(422);
    }

    public function test_unknown_element_fields_are_dropped_rather_than_persisted(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => $this->designPayload([
                'front' => ['elements' => [$this->textElement([
                    'onclick' => 'alert(1)',
                    'src' => 'https://evil.example.com/x.png',
                ])]],
            ]),
        ])->assertOk();

        $element = CustomDesignDraft::firstOrFail()->elements('front')[0];
        $this->assertArrayNotHasKey('onclick', $element);
        $this->assertArrayNotHasKey('src', $element);
    }

    public function test_inactive_product_cannot_be_designed(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $product->update(['status' => 'inactive']);

        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => $this->designPayload(),
        ])->assertStatus(422);
    }

    // ---------------------------------------------------------------------
    // Asset upload + secure download
    // ---------------------------------------------------------------------

    public function test_customer_uploads_an_image_asset_and_receives_an_authorized_url(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($customer, $product);

        $response = $this->actingAs($customer->user)->post(
            route('custom-designs.assets.store', $draft),
            ['image' => $this->fakePng()],
            ['Accept' => 'application/json'],
        );

        $response->assertOk()->assertJsonStructure(['id', 'url', 'width', 'height', 'original_filename']);
        $this->assertStringContainsString('/custom-designs/assets/', $response->json('url'));
        $this->assertSame(1, $response->json('width'));
        $this->assertSame(1, $response->json('height'));

        $asset = CustomDesignAsset::firstOrFail();
        $this->assertSame($draft->id, $asset->draft_id);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_asset_upload_rejects_non_image_and_oversized_files(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($customer, $product);

        $this->actingAs($customer->user)->post(
            route('custom-designs.assets.store', $draft),
            ['image' => UploadedFile::fake()->create('payload.pdf', 100, 'application/pdf')],
            ['Accept' => 'application/json'],
        )->assertStatus(422);

        $this->actingAs($customer->user)->post(
            route('custom-designs.assets.store', $draft),
            ['image' => UploadedFile::fake()->create('huge.png', 20000, 'image/png')],
            ['Accept' => 'application/json'],
        )->assertStatus(422);
    }

    public function test_asset_is_served_to_owner_and_admin_but_not_to_another_customer(): void
    {
        Storage::fake('local');
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $admin = $this->makeAdmin();
        $product = $this->makeProduct();
        $draft = $this->createDraft($owner, $product);

        $assetId = $this->actingAs($owner->user)->post(
            route('custom-designs.assets.store', $draft),
            ['image' => $this->fakePng()],
            ['Accept' => 'application/json'],
        )->json('id');

        $this->actingAs($owner->user)->get(route('custom-designs.show', $assetId))->assertOk();
        $this->actingAs($admin)->get(route('custom-designs.show', $assetId))->assertOk();
        $this->actingAs($intruder->user)->get(route('custom-designs.show', $assetId))->assertForbidden();
    }

    public function test_asset_url_never_exposes_the_server_filesystem_path(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($customer, $product);

        $asset = $this->actingAs($customer->user)->post(
            route('custom-designs.assets.store', $draft),
            ['image' => $this->fakePng()],
            ['Accept' => 'application/json'],
        )->json('id');

        $model = CustomDesignAsset::findOrFail($asset);
        $this->actingAs($customer->user)
            ->get(route('custom-designs.show', $asset))
            ->assertOk()
            ->assertHeaderMissing('X-Debug')
            ->assertDontSee($model->path, false);
    }

    // ---------------------------------------------------------------------
    // Cart integration
    // ---------------------------------------------------------------------

    public function test_adding_to_cart_stores_the_design_configuration_and_linked_draft(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($customer, $product, [
            'front' => ['elements' => [$this->textElement()]],
        ]);

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'production_method' => 'digital',
            'design_draft_id' => $draft->id,
        ])->assertRedirect(route('cart.index'));

        $item = Cart::firstOrFail()->items()->firstOrFail();
        $this->assertSame($draft->id, $item->custom_design_draft_id);
        $this->assertSame($draft->id, $item->custom_parameters['design_draft_id']);
        $this->assertNotEmpty($item->custom_parameters['design_configuration']);
        $this->assertSame('completed', $draft->fresh()->status);
    }

    public function test_customer_cannot_reference_another_customers_draft_in_cart(): void
    {
        Storage::fake('local');
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($owner, $product);

        $this->actingAs($intruder->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 1,
            'production_method' => 'digital',
            'design_draft_id' => $draft->id,
        ])->assertNotFound();
    }

    public function test_price_is_recalculated_server_side_and_ignores_any_client_amount(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(price: 10000);
        $draft = $this->createDraft($customer, $product);

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
            'production_method' => 'digital',
            'design_draft_id' => $draft->id,
            'price_at_addition' => 1,
            'total' => 1,
        ])->assertRedirect(route('cart.index'));

        $item = Cart::firstOrFail()->items()->firstOrFail();
        $this->assertSame(30000.0, (float) $item->price_at_addition);
    }

    public function test_reopening_a_cart_item_reloads_the_editor_with_its_draft(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(attributes: [
            'front_mockup' => 'images/mockups/product-front.svg',
            'back_mockup' => 'images/mockups/product-back.svg',
        ]);
        $draft = $this->createDraft($customer, $product, [
            'front' => ['elements' => [$this->textElement()]],
        ]);

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'production_method' => 'digital',
            'design_draft_id' => $draft->id,
        ])->assertRedirect(route('cart.index'));

        $item = Cart::firstOrFail()->items()->firstOrFail();

        // "Edit" in the cart redirects back into the editor carrying the draft.
        $this->actingAs($customer->user)
            ->get(route('cart.customize', $item))
            ->assertRedirect(route('products.customize', [
                'product' => $product->slug,
                'draft' => $draft->id,
                'cart_item' => $item->id,
            ]));

        // The editor page renders with the design restored and the draft id wired in.
        $this->actingAs($customer->user)
            ->get(route('products.customize', [
                'product' => $product->slug,
                'draft' => $draft->id,
                'cart_item' => $item->id,
            ]))
            ->assertOk()
            ->assertSee('Promo Kilat Print')
            ->assertSee($draft->id, false);
    }

    public function test_editing_a_cart_item_updates_it_instead_of_creating_a_duplicate(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $cart = $this->makeCart($customer);
        $existing = $this->makeCartItem($cart, $product, 1);
        $draft = $this->createDraft($customer, $product);

        $this->actingAs($customer->user)->post(route('cart.store'), [
            'product_id' => $product->id,
            'quantity' => 9,
            'production_method' => 'digital',
            'design_draft_id' => $draft->id,
            'editing_cart_item_id' => $existing->id,
        ])->assertRedirect(route('cart.index'));

        $this->assertCount(1, $cart->fresh()->items);
        $this->assertSame(9, $existing->fresh()->quantity);
        $this->assertSame($draft->id, $existing->fresh()->custom_design_draft_id);
    }

    public function test_design_is_persisted_under_the_canonical_side_shape(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => [
                'front' => ['elements' => [$this->textElement()]],
                'back' => ['elements' => []],
            ],
        ])->assertOk()->assertJsonPath('design.front.elements.0.text', 'Promo Kilat Print');

        $draft = CustomDesignDraft::firstOrFail();
        $this->assertArrayHasKey('elements', $draft->design['front']);
        $this->assertCount(1, $draft->elements('front'));
    }

    /**
     * A shorthand `{front: [...]}` payload must not silently persist an empty design.
     */
    public function test_shorthand_side_arrays_are_normalised_instead_of_being_dropped(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'design' => [
                'front' => [$this->textElement()],
                'back' => [$this->stickerElement()],
            ],
        ])->assertOk();

        $draft = CustomDesignDraft::firstOrFail();
        $this->assertCount(1, $draft->elements('front'));
        $this->assertCount(1, $draft->elements('back'));
        $this->assertSame('Promo Kilat Print', $draft->elements('front')[0]['text']);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    protected function createDraft($customer, Product $product, ?array $design = null): CustomDesignDraft
    {
        $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'specification' => ['quantity' => 1, 'production_method' => 'digital'],
            'design' => $design ?? $this->designPayload(),
        ])->assertOk();

        return CustomDesignDraft::latest('created_at')->firstOrFail();
    }
}
