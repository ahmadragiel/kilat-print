<?php

namespace Tests\Feature;

use Tests\TestCase;

class RenderedMarkupTest extends TestCase
{
    /**
     * A Blade component that fails to compile leaks literal `<x-...>` tags into the HTML,
     * which Alpine then tries to evaluate as an expression and throws at runtime. This
     * guard makes that class of bug a hard test failure instead of a silent console error.
     */
    protected function assertNoRawComponents(string $html, string $context): void
    {
        $this->assertStringNotContainsString('<x-', $html, "Raw Blade component leaked into {$context}.");
    }

    public function test_public_pages_render_without_leaked_components(): void
    {
        $product = $this->makeProduct(attributes: ['name' => 'Spanduk Uji', 'slug' => 'spanduk-uji']);

        foreach (['/', '/products', '/login', '/register'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $this->assertNoRawComponents($response->getContent(), $url);
        }

        $response = $this->get(route('products.show', $product));
        $response->assertOk();
        $this->assertNoRawComponents($response->getContent(), 'product show');
    }

    public function test_customer_pages_render_without_leaked_components(): void
    {
        $customer = $this->makeCustomer();

        foreach (['/dashboard', '/cart', '/orders', '/addresses', '/notifications', '/profile'] as $url) {
            $response = $this->actingAs($customer->user)->get($url);
            $response->assertOk();
            $this->assertNoRawComponents($response->getContent(), $url);
        }

        $product = $this->makeProduct();
        $response = $this->actingAs($customer->user)->get(route('products.customize', $product));
        $response->assertOk();
        $this->assertNoRawComponents($response->getContent(), 'design editor');
    }

    public function test_admin_pages_render_without_leaked_components(): void
    {
        $admin = $this->makeAdmin();

        foreach ([
            '/admin/dashboard', '/admin/orders', '/admin/products', '/admin/materials',
            '/admin/reports', '/admin/production', '/admin/payments', '/admin/customers',
            '/admin/designs', '/admin/operators', '/admin/products/create',
        ] as $url) {
            $response = $this->actingAs($admin)->get($url);
            $response->assertOk();
            $this->assertNoRawComponents($response->getContent(), $url);
        }
    }

    public function test_admin_order_detail_renders_without_leaked_components(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer);
        $this->makeOrderItem($order, $product);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));
        $response->assertOk();
        $this->assertNoRawComponents($response->getContent(), 'admin order detail');
    }

    public function test_operator_pages_render_without_leaked_components(): void
    {
        $operator = $this->makeOperator();

        foreach (['/operator/dashboard', '/operator/jobs'] as $url) {
            $response = $this->actingAs($operator->user)->get($url);
            $response->assertOk();
            $this->assertNoRawComponents($response->getContent(), $url);
        }
    }

    public function test_design_editor_page_embeds_a_resolvable_configuration(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $response = $this->actingAs($customer->user)->get(route('products.customize', $product));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('designEditor(', $html);
        $this->assertStringContainsString('data-design-editor-action="continue"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        // The preview input must be a real file input, not a duplicate-typed hidden one.
        $this->assertMatchesRegularExpression('/<input[^>]*name="design_file"[^>]*type="file"/', $html);
    }
}
