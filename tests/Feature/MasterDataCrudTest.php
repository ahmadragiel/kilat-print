<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Finishing;
use App\Models\Material;
use App\Models\PriceRule;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MasterDataCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_soft_delete_a_product(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::create(['name' => 'Kategori Uji', 'slug' => 'kategori-uji', 'status' => 'active']);
        $material = Material::create(['name' => 'Material Uji Per Sqm', 'slug' => 'material-uji-per-sqm', 'pricing_type' => 'per_sqm', 'price' => 25000, 'status' => 'active']);
        $finishing = Finishing::create(['name' => 'Finishing Uji', 'slug' => 'finishing-uji', 'pricing_type' => 'per_item', 'price' => 2000, 'status' => 'active']);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Produk Uji Demo',
            'slug' => 'produk-uji-demo',
            'category_id' => $category->id,
            'description' => 'Demo product.',
            'status' => 'active',
            'minimum_order' => 1,
            'production_days' => 3,
            'materials' => [$material->id],
            'finishings' => [$finishing->id],
        ])->assertRedirect(route('admin.products.index'));

        $product = Product::where('slug', 'produk-uji-demo')->firstOrFail();
        $this->assertDatabaseHas('product_materials', ['product_id' => $product->id, 'material_id' => $material->id]);
        $this->assertDatabaseHas('product_finishings', ['product_id' => $product->id, 'finishing_id' => $finishing->id]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => 'Produk Uji Demo Updated',
            'slug' => 'produk-uji-demo-updated',
            'category_id' => $category->id,
            'description' => 'Updated demo product.',
            'status' => 'inactive',
            'minimum_order' => 2,
            'production_days' => 4,
            'materials' => [$material->id],
            'finishings' => [$finishing->id],
        ])->assertRedirect(route('admin.products.index'));
        $this->assertSame('Produk Uji Demo Updated', $product->fresh()->name);
        $this->assertSame('inactive', $product->fresh()->status);

        $this->actingAs($admin)->delete(route('admin.products.destroy', $product->fresh()))->assertSessionHas('success');
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_create_update_and_soft_delete_categories_materials_and_finishings(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Event', 'slug' => 'event', 'description' => 'Demo category.', 'status' => 'active',
        ])->assertRedirect(route('admin.categories.index'));
        $category = Category::where('slug', 'event')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Event Updated', 'slug' => 'event-updated', 'status' => 'inactive',
        ])->assertRedirect(route('admin.categories.index'));
        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category->fresh()));
        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        $this->actingAs($admin)->post(route('admin.materials.store'), [
            'name' => 'Art Paper', 'slug' => 'art-paper', 'pricing_type' => 'per_item', 'price' => 3500, 'status' => 'active',
        ])->assertRedirect(route('admin.materials.index'));
        $material = Material::where('slug', 'art-paper')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.materials.update', $material), [
            'name' => 'Art Paper Updated', 'pricing_type' => 'per_sqm', 'price' => 4500, 'status' => 'inactive',
        ])->assertRedirect(route('admin.materials.index'));
        $this->actingAs($admin)->delete(route('admin.materials.destroy', $material->fresh()));
        $this->assertSoftDeleted('materials', ['id' => $material->id]);

        $this->actingAs($admin)->post(route('admin.finishings.store'), [
            'name' => 'Laminasi', 'slug' => 'laminasi', 'pricing_type' => 'per_sqm', 'price' => 12000, 'status' => 'active',
        ])->assertRedirect(route('admin.finishings.index'));
        $finishing = Finishing::where('slug', 'laminasi')->firstOrFail();
        $this->actingAs($admin)->put(route('admin.finishings.update', $finishing), [
            'name' => 'Laminasi Updated', 'pricing_type' => 'per_item', 'price' => 5000, 'status' => 'inactive',
        ])->assertRedirect(route('admin.finishings.index'));
        $this->actingAs($admin)->delete(route('admin.finishings.destroy', $finishing->fresh()));
        $this->assertSoftDeleted('finishings', ['id' => $finishing->id]);
    }

    public function test_admin_can_create_update_and_deactivate_price_rules(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct();

        $this->actingAs($admin)->post(route('admin.prices.store'), [
            'product_id' => $product->id,
            'name' => 'Area tariff',
            'pricing_type' => 'per_sqm',
            'price' => 25000,
            'discount_percent' => 15.5,
            'min_quantity' => 1,
            'active' => '1',
        ])->assertRedirect(route('admin.prices.index'));
        $rule = PriceRule::where('name', 'Area tariff')->firstOrFail();
        $this->assertTrue($rule->active);
        $this->assertSame('15.50', $rule->discount_percent);
        $this->actingAs($admin)->get(route('admin.prices.edit', $rule))
            ->assertOk()
            ->assertSee('<option value="'.$product->id.'" selected', false);

        $this->actingAs($admin)->put(route('admin.prices.update', $rule), [
            'product_id' => $product->id,
            'name' => 'Area tariff updated',
            'pricing_type' => 'per_sqm',
            'price' => 27500,
            'discount_percent' => 20,
            'min_quantity' => 2,
        ])->assertRedirect(route('admin.prices.index'));
        $this->assertFalse($rule->fresh()->active);
        $this->assertSame('20.00', $rule->fresh()->discount_percent);

        $this->actingAs($admin)->delete(route('admin.prices.destroy', $rule))->assertSessionHas('success');
        $this->assertFalse($rule->fresh()->active);
    }

    public function test_product_edit_keeps_every_specification_key(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(attributes: [
            'specifications' => [
                'ukuran' => 'A3',
                'bahan' => 'Art Paper',
                'catatan' => 'Catatan internal',
            ],
        ]);

        $this->actingAs($admin)->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('ukuran|A3');

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'status' => 'active',
            'minimum_order' => 1,
            'production_days' => 2,
            'specifications' => "ukuran|A3\nbahan|Art Paper 260gsm\ncatatan|Catatan internal",
        ])->assertRedirect(route('admin.products.index'));

        $this->assertSame([
            'ukuran' => 'A3',
            'bahan' => 'Art Paper 260gsm',
            'catatan' => 'Catatan internal',
        ], $product->fresh()->specifications);
    }

    public function test_admin_can_mark_a_product_variation_as_optional(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(attributes: [
            'variations' => [
                'key' => 'size',
                'label' => 'Ukuran',
                'required' => true,
                'options' => [['value' => 'small', 'label' => 'Small']],
            ],
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'status' => 'active',
            'minimum_order' => 1,
            'production_days' => 2,
            'variations' => [
                'key' => 'size',
                'label' => 'Ukuran',
                'required' => '0',
                'options' => "small|Small\nlarge|Large",
            ],
        ])->assertRedirect(route('admin.products.index'));

        $variations = $product->fresh()->variations;
        $this->assertFalse($variations['required']);
        $this->assertCount(2, $variations['options']);

        $page = $this->get(route('products.show', $product->fresh()))->assertOk()->getContent();

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($page);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($document);
        $select = $xpath->query('//select[@name="variant"]')->item(0);

        $this->assertNotNull($select);
        $this->assertFalse($select->hasAttribute('required'));
        $this->assertNotNull($xpath->query('.//option[@value=""]', $select)->item(0));
    }

    public function test_replacing_a_product_thumbnail_deletes_the_old_file_after_save(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(attributes: ['thumbnail' => 'thumbnails/old.png']);
        Storage::disk('public')->put('thumbnails/old.png', 'old image');

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'category_id' => $product->category_id,
            'status' => 'active',
            'minimum_order' => 1,
            'production_days' => 2,
            'thumbnail' => $this->fakePng('new-thumbnail.png'),
        ])->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertNotSame('thumbnails/old.png', $product->thumbnail);
        Storage::disk('public')->assertMissing('thumbnails/old.png');
        Storage::disk('public')->assertExists($product->thumbnail);
    }

    public function test_a_failed_product_update_keeps_the_existing_thumbnail(): void
    {
        Storage::fake('public');
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(attributes: ['thumbnail' => 'thumbnails/old.png']);
        Storage::disk('public')->put('thumbnails/old.png', 'old image');

        $this->actingAs($admin)
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'name' => '',
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'status' => 'active',
                'minimum_order' => 1,
                'production_days' => 2,
                'thumbnail' => $this->fakePng('new-thumbnail.png'),
            ])
            ->assertSessionHasErrors('name');

        Storage::disk('public')->assertExists('thumbnails/old.png');
        $this->assertSame('thumbnails/old.png', $product->fresh()->thumbnail);
    }

    public function test_deleting_a_product_deactivates_its_price_rules_without_deleting_them(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct();
        $rule = PriceRule::create([
            'product_id' => $product->id,
            'name' => 'Harga produk',
            'pricing_type' => 'per_item',
            'price' => 15000,
            'min_quantity' => 1,
            'active' => true,
            'is_wholesale' => false,
        ]);

        $this->actingAs($admin)->delete(route('admin.products.destroy', $product))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertFalse($rule->fresh()->active);
        $this->assertDatabaseHas('price_rules', ['id' => $rule->id]);
    }

    public function test_non_admin_cannot_manage_master_data(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)->get(route('admin.products.create'))->assertForbidden();
        $this->actingAs($customer->user)->post(route('admin.categories.store'), [
            'name' => 'Forbidden', 'slug' => 'forbidden', 'status' => 'active',
        ])->assertForbidden();
    }

    /**
     * A genuine 1x1 PNG so image validation works without the optional GD
     * extension.
     */
    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $path = tempnam(sys_get_temp_dir(), 'kilat-thumbnail-').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }
}
