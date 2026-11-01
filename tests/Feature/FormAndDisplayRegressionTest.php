<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Product;
use Tests\TestCase;

/**
 * Regressions for form/route mismatches and display defects that the existing
 * suite missed because it called controllers directly with the "correct" verb.
 */
class FormAndDisplayRegressionTest extends TestCase
{
    public function test_product_detail_form_is_multipart_for_the_optional_design_upload(): void
    {
        $product = $this->makeProduct();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_profile_form_uses_put_and_saves_the_customer_phone(): void
    {
        $customer = $this->makeCustomer(['name' => 'Profile Owner']);

        $this->actingAs($customer->user)
            ->get(route('customer.profile.edit'))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false);

        $this->actingAs($customer->user)
            ->put(route('customer.profile.update'), [
                'name' => 'Profile Owner Updated',
                'email' => $customer->user->email,
                'phone' => '081299998888',
            ])
            ->assertSessionHas('success');

        $this->assertSame('081299998888', $customer->fresh()->phone);
    }

    public function test_address_form_uses_put_and_saves_label_and_address_line2(): void
    {
        $customer = $this->makeCustomer();
        $address = $this->makeAddress($customer);

        $this->actingAs($customer->user)
            ->get(route('customer.addresses.index'))
            ->assertOk()
            ->assertSee('name="_method" value="PUT"', false);

        $this->actingAs($customer->user)
            ->put(route('customer.addresses.update', $address), [
                'label' => 'Kantor',
                'recipient' => 'Penerima Baru',
                'phone' => '081277776666',
                'address' => 'Jalan Baru No. 2',
                'address_line2' => 'Lantai 2',
                'district' => 'Gubeng',
                'city' => 'Surabaya',
                'province' => 'Jawa Timur',
                'postal_code' => '60281',
                'is_primary' => '1',
            ])
            ->assertSessionHas('success');

        $fresh = $address->fresh();
        $this->assertSame('Kantor', $fresh->label);
        $this->assertSame('Lantai 2', $fresh->address_line2);
    }

    public function test_order_detail_shows_the_order_number_instead_of_the_database_id(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $order->forceFill(['number' => 'KP-20261025-0042'])->save();

        $response = $this->actingAs($customer->user)->get(route('customer.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Pesanan #KP-20261025-0042');
        $response->assertDontSee('Pesanan #'.$order->id);
    }

    public function test_customer_dashboard_does_not_render_a_stray_angle_bracket(): void
    {
        $customer = $this->makeCustomer();
        $this->makeOrder($customer, ['status' => OrderStatus::PendingPayment]);

        $content = str_replace("\r\n", "\n", $this->actingAs($customer->user)
            ->get(route('customer.dashboard'))
            ->assertOk()
            ->getContent());

        $this->assertStringNotContainsString("</div>\n    >", $content);
    }

    public function test_admin_can_remove_the_featured_flag_by_leaving_the_checkbox_unchecked(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->makeProduct(attributes: ['is_featured' => true]);

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), [
                'name' => $product->name,
                'slug' => $product->slug,
                'category_id' => $product->category_id,
                'description' => $product->description,
                'status' => 'active',
                'minimum_order' => 1,
                'production_days' => 2,
            ])
            ->assertRedirect(route('admin.products.index'));

        $this->assertFalse($product->fresh()->is_featured);
    }

    public function test_generated_slugs_are_non_empty_and_unique_when_the_name_repeats(): void
    {
        $admin = $this->makeAdmin();
        $category = Category::create([
            'name' => 'Kategori Slug',
            'slug' => 'kategori-slug',
            'status' => 'active',
        ]);

        $payload = [
            'name' => '!!!',
            'slug' => '',
            'category_id' => $category->id,
            'status' => 'active',
            'minimum_order' => 1,
            'production_days' => 2,
        ];

        $this->actingAs($admin)->post(route('admin.products.store'), $payload)->assertRedirect();
        $this->actingAs($admin)->post(route('admin.products.store'), $payload)->assertRedirect();

        $products = Product::query()->where('name', '!!!')->orderBy('id')->get();

        $this->assertCount(2, $products);
        $this->assertNotSame('', $products[0]->slug);
        $this->assertNotSame('', $products[1]->slug);
        $this->assertNotSame($products[0]->slug, $products[1]->slug);
    }
}
