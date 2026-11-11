<?php

namespace Tests\Feature;

use App\Enums\DesignStatus;
use App\Enums\PaymentMethod;
use App\Enums\PricingType;
use App\Enums\ProductionStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\RepeatOrderService;
use Database\Seeders\RealCatalogSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

class RepeatOrderTest extends TestCase
{
    private FakeMidtransApi $midtrans;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();
        config(['printing.delivery_fee' => 15000]);

        $this->midtrans = new FakeMidtransApi;
        $this->midtrans->install();
    }

    public function test_repeat_order_keeps_the_kertas_kado_material_variant_and_recalculates_its_price(): void
    {
        $this->seed(RealCatalogSeeder::class);
        $customer = $this->makeCustomer();
        $product = $this->catalogProduct('kertas-kado-custom');
        $source = $this->makeOrder($customer);
        $this->makeOrderItem($source, $product, [
            'quantity' => 2,
            'size' => 'A3',
            'variant' => 'artpaper',
            'variant_label' => 'Label lama yang tidak boleh dipakai',
            'length_cm' => 30,
            'width_cm' => 42,
            'color' => 'Blue',
            'production_method' => 'offset',
            'custom_parameters' => ['design_version' => 7, 'finish' => 'matte'],
            'notes' => 'Keep this configuration.',
            'unit_price' => 1,
            'line_total' => 1,
        ]);
        $this->makePayment($source);
        $orderCountBefore = $customer->orders()->count();
        $paymentCountBefore = $source->payment()->count();

        $response = $this->actingAs($customer->user)
            ->post(route('customer.orders.repeat', $source));

        $response->assertRedirect(route('cart.index'));
        $item = $customer->fresh()->cart->items()->sole();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('A3', $item->size);
        $this->assertSame('artpaper', $item->variant);
        $this->assertSame('ArtPaper', $item->variant_label);
        $this->assertSame('Blue', $item->color);
        $this->assertSame('offset', $item->production_method);
        $this->assertSame('Keep this configuration.', $item->notes);
        $this->assertNull($item->material_id);
        $this->assertNull($item->finishing_id);
        $this->assertSame(30000, (int) $item->price_at_addition);
        $this->assertSame(['design_version' => 7, 'finish' => 'matte'], $item->custom_parameters);
        $this->assertSame($orderCountBefore, $customer->orders()->count());
        $this->assertSame($paymentCountBefore, $source->payment()->count());
    }

    public function test_repeat_order_reuses_every_paper_bag_size_variant(): void
    {
        $this->seed(RealCatalogSeeder::class);
        $customer = $this->makeCustomer();
        $product = $this->catalogProduct('paper-bag-custom');
        $source = $this->makeOrder($customer);

        $expected = [
            'small' => ['Small', 19500],
            'medium' => ['Medium', 24500],
            'large' => ['Large', 32000],
            'large-x2' => ['Large X2', 39500],
        ];

        foreach (array_keys($expected) as $variant) {
            $this->makeOrderItem($source, $product, [
                'quantity' => 1,
                'variant' => $variant,
                'variant_label' => 'Label lama',
                'unit_price' => 1,
                'line_total' => 1,
            ]);
        }

        $result = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));
        $items = $result->cart->items->keyBy('variant');

        $this->assertCount(4, $items);
        foreach ($expected as $variant => [$label, $price]) {
            $this->assertSame($label, $items[$variant]->variant_label);
            $this->assertSame($price, (int) $items[$variant]->price_at_addition);
        }
    }

    public function test_repeat_order_reuses_every_topi_model_variant(): void
    {
        $this->seed(RealCatalogSeeder::class);
        $customer = $this->makeCustomer();
        $product = $this->catalogProduct('topi-ultah-custom');
        $source = $this->makeOrder($customer);

        $expected = [
            'love' => 'Love',
            'polkadot' => 'Polkadot',
            'muka-polos' => 'Muka Polos',
            'bintang' => 'Bintang',
        ];

        foreach (array_keys($expected) as $variant) {
            $this->makeOrderItem($source, $product, [
                'variant' => $variant,
                'variant_label' => 'Label lama',
                'unit_price' => 1,
                'line_total' => 1,
            ]);
        }

        $result = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));
        $items = $result->cart->items->keyBy('variant');

        $this->assertCount(4, $items);
        foreach ($expected as $variant => $label) {
            $this->assertSame($label, $items[$variant]->variant_label);
            $this->assertSame(12900, (int) $items[$variant]->price_at_addition);
        }
    }

    public function test_repeat_order_keeps_mug_without_a_variant(): void
    {
        $this->seed(RealCatalogSeeder::class);
        $customer = $this->makeCustomer();
        $product = $this->catalogProduct('gelas-mug-custom');
        $source = $this->makeOrder($customer);
        $this->makeOrderItem($source, $product, [
            'variant' => null,
            'variant_label' => null,
            'unit_price' => 1,
            'line_total' => 1,
        ]);

        $result = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));
        $item = $result->cart->items->sole();

        $this->assertNull($item->variant);
        $this->assertNull($item->variant_label);
        $this->assertSame(29500, (int) $item->price_at_addition);
    }

    public function test_repeat_order_prefers_the_items_own_final_design_reference(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($customer);
        $primary = $this->putFinalDesign($customer, 'primary-final.png');
        $fallback = $this->putFinalDesign($customer, 'approved-fallback.png');
        $item = $this->makeOrderItem($source, $product, [
            'design_reference' => $primary,
            'custom_parameters' => ['design_original_filename' => 'primary-final.png'],
        ]);
        $this->makeDesign($source, [
            'order_item_id' => $item->id,
            'path' => $fallback,
            'status' => DesignStatus::Approved,
            'version' => 4,
        ]);

        $result = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));

        $this->assertSame($primary, $result->cart->items->sole()->design_reference);
        $this->assertSame(1, $result->reusedDesigns);
        $this->assertSame([], $result->missingDesigns);
        $this->assertTrue(Storage::disk('local')->exists($primary));
        $this->assertCount(2, Storage::disk('local')->allFiles('design-references'));
    }

    public function test_repeat_order_keeps_each_items_own_design_when_several_products_are_repeated(): void
    {
        $customer = $this->makeCustomer();
        $firstProduct = $this->makeProduct(attributes: ['name' => 'Produk Pertama']);
        $secondProduct = $this->makeProduct(attributes: ['name' => 'Produk Kedua']);
        $source = $this->makeOrder($customer);
        $firstDesign = $this->putFinalDesign($customer, 'first-product.png');
        $secondDesign = $this->putFinalDesign($customer, 'second-product.png');
        $decoyDesign = $this->putFinalDesign($customer, 'other-item.png');

        $firstItem = $this->makeOrderItem($source, $firstProduct, [
            'design_reference' => $firstDesign,
        ]);
        $secondItem = $this->makeOrderItem($source, $secondProduct, [
            'design_reference' => null,
        ]);

        $this->makeDesign($source, [
            'order_item_id' => $firstItem->id,
            'path' => $decoyDesign,
            'status' => DesignStatus::Approved,
        ]);
        $this->makeDesign($source, [
            'order_item_id' => $secondItem->id,
            'path' => $secondDesign,
            'status' => DesignStatus::Approved,
            'version' => 2,
        ]);

        $result = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));
        $items = $result->cart->items->keyBy('product_id');

        $this->assertSame($firstDesign, $items[$firstProduct->id]->design_reference);
        $this->assertSame($secondDesign, $items[$secondProduct->id]->design_reference);
        $this->assertSame(2, $result->reusedDesigns);
        $this->assertSame([], $result->missingDesigns);
    }

    public function test_repeat_order_without_a_stored_final_design_warns_and_keeps_the_reference_empty(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(attributes: ['name' => 'Produk Tanpa Desain']);
        $source = $this->makeOrder($customer);
        $missing = 'design-references/'.$customer->id.'/missing-final.png';
        $item = $this->makeOrderItem($source, $product, [
            'design_reference' => $missing,
        ]);
        $this->makeDesign($source, [
            'order_item_id' => $item->id,
            'path' => 'design-references/'.$customer->id.'/also-missing.png',
            'status' => DesignStatus::Approved,
        ]);

        $response = $this->actingAs($customer->user)
            ->post(route('customer.orders.repeat', $source));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('warning');
        $warning = (string) session('warning');
        $this->assertStringContainsString('Produk Tanpa Desain', $warning);
        $this->assertStringContainsString('unggah ulang', strtolower($warning));

        $cartItem = $customer->fresh()->cart->items()->sole();
        $this->assertNull($cartItem->design_reference);
        $this->assertSame($missing, $source->items()->sole()->design_reference);
        $this->assertSame([], Storage::disk('local')->allFiles('design-references'));
    }

    public function test_cart_after_repeat_previews_the_reused_final_design(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($customer);
        $path = $this->putFinalDesign($customer, 'preview-final.png');
        $this->makeOrderItem($source, $product, ['design_reference' => $path]);

        $this->actingAs($customer->user)
            ->post(route('customer.orders.repeat', $source))
            ->assertRedirect(route('cart.index'));

        $cartItem = $customer->fresh()->cart->items()->sole();

        $this->actingAs($customer->user)
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee(route('cart.design-preview', $cartItem), false)
            ->assertSee('Lihat desain final');

        $preview = $this->actingAs($customer->user)
            ->get(route('cart.design-preview', $cartItem));

        $preview->assertOk();
        $preview->assertHeader('Content-Type', 'image/png');
        $this->assertSame($this->pngBytes(), $preview->streamedContent());
    }

    public function test_checkout_after_repeat_creates_a_new_design_file_for_the_new_order(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $source = $this->makeOrder($customer);
        $path = $this->putFinalDesign($customer, 'repeat-checkout.png');
        $sourceItem = $this->makeOrderItem($source, $product, [
            'design_reference' => $path,
            'custom_parameters' => ['design_original_filename' => 'repeat-checkout.png'],
        ]);
        $this->makeDesign($source, [
            'order_item_id' => $sourceItem->id,
            'path' => $path,
            'original_filename' => 'repeat-checkout.png',
            'status' => DesignStatus::Approved,
        ]);

        $newOrder = $this->repeatAndCheckout($customer, $source);
        $newItem = $newOrder->items()->sole();
        $newDesign = $newOrder->designFiles()->sole();

        $this->assertSame($path, $newItem->design_reference);
        $this->assertSame($newOrder->id, $newDesign->order_id);
        $this->assertSame($newItem->id, $newDesign->order_item_id);
        $this->assertSame($path, $newDesign->path);
        $this->assertSame('repeat-checkout.png', $newDesign->original_filename);
        $this->assertSame(DesignStatus::Approved, $newDesign->status);
        $this->assertSame(1, $source->designFiles()->count());
        $this->assertSame($path, $source->items()->sole()->design_reference);
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_admin_and_assigned_operator_can_download_the_repeated_final_design(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($customer);
        $path = $this->putFinalDesign($customer, 'download-final.png');
        $item = $this->makeOrderItem($source, $product, [
            'design_reference' => $path,
            'custom_parameters' => ['design_original_filename' => 'download-final.png'],
        ]);
        $this->makeDesign($source, [
            'order_item_id' => $item->id,
            'path' => $path,
            'original_filename' => 'download-final.png',
            'status' => DesignStatus::Approved,
        ]);

        $newOrder = $this->repeatAndCheckout($customer, $source);
        $design = $newOrder->designFiles()->sole();
        $assigned = $this->makeOperator();
        $newOrder->production()->create([
            'operator_id' => $assigned->id,
            'status' => ProductionStatus::IN_DESIGN->value,
            'progress' => 0,
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.designs.download', $design))
            ->assertOk()
            ->assertDownload('download-final.png');

        $this->actingAs($assigned->user)
            ->get(route('operator.designs.download', $design))
            ->assertOk()
            ->assertDownload('download-final.png');
    }

    public function test_operator_without_an_assignment_cannot_download_the_repeated_final_design(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($customer);
        $path = $this->putFinalDesign($customer, 'private-final.png');
        $this->makeOrderItem($source, $product, ['design_reference' => $path]);

        $newOrder = $this->repeatAndCheckout($customer, $source);
        $design = $newOrder->designFiles()->sole();

        $this->actingAs($this->makeOperator()->user)
            ->get(route('operator.designs.download', $design))
            ->assertForbidden();
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

    public function test_repeat_order_recalculates_against_current_catalog_prices(): void
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

        $result = app(RepeatOrderService::class)->repeat($customer, $source->load('items.product'));

        $this->assertSame(25000, (int) $result->cart->items()->sole()->price_at_addition);
    }

    public function test_repeat_order_leaves_the_source_order_items_and_files_untouched(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($customer);
        $path = $this->putFinalDesign($customer, 'historical-final.png');
        $item = $this->makeOrderItem($source, $product, [
            'quantity' => 3,
            'variant' => null,
            'design_reference' => $path,
            'custom_parameters' => ['design_original_filename' => 'historical-final.png'],
            'notes' => 'Historical notes',
            'unit_price' => 12345,
            'line_total' => 37035,
        ]);
        $design = $this->makeDesign($source, [
            'order_item_id' => $item->id,
            'path' => $path,
            'original_filename' => 'historical-final.png',
            'status' => DesignStatus::Approved,
        ]);
        $bytes = $this->pngBytes();
        Storage::disk('local')->put($path, $bytes);
        $orderBefore = $source->fresh()->getAttributes();
        $itemBefore = $item->fresh()->getAttributes();
        $designBefore = $design->fresh()->getAttributes();

        $this->repeatAndCheckout($customer, $source);

        $source->refresh();
        $this->assertSame($orderBefore, $source->getAttributes());
        $this->assertSame($itemBefore, $item->fresh()->getAttributes());
        $this->assertSame($designBefore, $design->fresh()->getAttributes());
        $this->assertSame($bytes, Storage::disk('local')->get($path));
        $this->assertSame(1, $source->items()->count());
        $this->assertSame(1, $source->designFiles()->count());
    }

    public function test_repeat_order_replaces_an_existing_cart_and_removes_only_orphaned_cart_files(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $source = $this->makeOrder($customer);
        $sourceDesign = $this->putFinalDesign($customer, 'source-final.png');
        $this->makeOrderItem($source, $product, ['design_reference' => $sourceDesign]);
        $this->makeDesign($source, [
            'path' => $sourceDesign,
            'status' => DesignStatus::Approved,
        ]);

        $orphan = $this->putFinalDesign($customer, 'old-orphan.png');
        $shared = $this->putFinalDesign($customer, 'shared-with-design-file.png');
        $legacy = 'designs/legacy-orphan.png';
        Storage::disk('local')->put($legacy, $this->pngBytes());
        $this->makeDesign($source, [
            'path' => $shared,
            'status' => DesignStatus::Approved,
            'version' => 2,
        ]);

        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, 1, ['design_reference' => $orphan]);
        $this->makeCartItem($cart, $product, 1, ['design_reference' => $shared]);
        $this->makeCartItem($cart, $product, 1, ['design_reference' => $legacy]);

        $this->actingAs($customer->user)
            ->get(route('customer.orders.show', $source))
            ->assertOk()
            ->assertSee('Pesan ulang akan mengganti seluruh isi keranjang saat ini dengan konfigurasi pesanan ini.', false);

        $response = $this->actingAs($customer->user)
            ->post(route('customer.orders.repeat', $source));

        $response->assertRedirect(route('cart.index'));
        $response->assertSessionHas('success');
        $this->assertStringContainsString('Seluruh isi keranjang diganti', (string) session('success'));

        $items = $customer->fresh()->cart->items()->get();
        $this->assertCount(1, $items);
        $this->assertSame($sourceDesign, $items->sole()->design_reference);
        $this->assertFalse(Storage::disk('local')->exists($orphan));
        $this->assertTrue(Storage::disk('local')->exists($shared));
        $this->assertTrue(Storage::disk('local')->exists($legacy));
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_repeat_order_rejects_a_removed_variant_without_guessing_another_one(): void
    {
        $this->seed(RealCatalogSeeder::class);
        $customer = $this->makeCustomer();
        $product = $this->catalogProduct('paper-bag-custom');
        $source = $this->makeOrder($customer);
        $this->makeOrderItem($source, $product, [
            'variant' => 'extra-large',
            'variant_label' => 'Extra Large',
        ]);
        $cart = $this->makeCart($customer);
        $existingPath = $this->putFinalDesign($customer, 'existing-cart.png');
        $existing = $this->makeCartItem($cart, $product, 1, [
            'design_reference' => $existingPath,
        ]);

        $response = $this->actingAs($customer->user)
            ->post(route('customer.orders.repeat', $source));

        $response->assertRedirect();
        $response->assertSessionHasErrors('variant');
        $message = (string) session('errors')->first('variant');
        $this->assertStringContainsString('Paper Bag Custom', $message);
        $this->assertStringContainsString('tidak tersedia', $message);
        $this->assertDatabaseHas('cart_items', ['id' => $existing->id]);
        $this->assertSame(1, $cart->items()->count());
        $this->assertTrue(Storage::disk('local')->exists($existingPath));
        $this->assertDatabaseCount('orders', 1);
    }

    private function repeatAndCheckout(Customer $customer, Order $source): Order
    {
        $this->actingAs($customer->user)
            ->post(route('customer.orders.repeat', $source))
            ->assertRedirect(route('cart.index'));

        $address = $this->makeAddress($customer);

        $this->actingAs($customer->user)
            ->post(route('checkout.store'), $this->checkoutPayload($customer, $address->id))
            ->assertRedirect();

        return $customer->orders()->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(Customer $customer, int $addressId): array
    {
        return [
            'name' => $customer->user->name,
            'phone' => '081200000001',
            'email' => $customer->user->email,
            'shipping_method' => 'delivery',
            'address_id' => $addressId,
            'payment_method' => PaymentMethod::Qris->value,
        ];
    }

    private function catalogProduct(string $slug): Product
    {
        return Product::query()->where('slug', $slug)->firstOrFail();
    }

    private function putFinalDesign(Customer $customer, string $filename): string
    {
        $path = 'design-references/'.$customer->id.'/'.$filename;
        Storage::disk('local')->put($path, $this->pngBytes());

        return $path;
    }

    private function pngBytes(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
}
