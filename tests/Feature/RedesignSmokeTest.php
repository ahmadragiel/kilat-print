<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\ProductionStatus;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTestData;
use Tests\TestCase;

/**
 * Temporary walk-through of every screen touched by the interface redesign.
 * It only asserts that each page renders (HTTP 200) with the seeded shapes of
 * real data, so missing variables, broken components and bad @php blocks surface
 * before the pages are checked by hand.
 */
class RedesignSmokeTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    public function test_guest_pages_render(): void
    {
        $product = $this->makeProduct();

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->makeOrderItem($order, $product);
        $this->makePaidOrder($order);
        $this->makeProduction($order, [
            'status' => ProductionStatus::PRINTING,
            'progress' => 40,
            'deadline' => now()->addDays(2),
        ]);
        $order->production->statusHistories()->create([
            'new_status' => ProductionStatus::PRINTING,
            'progress' => 40,
        ]);

        foreach ([
            '/',
            '/products',
            '/products/'.$product->slug,
            '/lacak',
            '/lacak?number='.$order->number,
            '/lacak?number=TIDAK-ADA',
            '/login',
            '/register',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_customer_pages_render(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeAddress($customer);

        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, quantity: 3);

        $order = $this->makePaidOrder($this->makeOrder($customer));
        $this->makeOrderItem($order, $product);
        $this->makeOrderAddress($order);

        $pending = $this->makeOrder($customer, ['grand_total' => 25000, 'subtotal' => 25000]);
        $this->makeOrderItem($pending, $product);
        $this->makeOrderAddress($pending);
        $this->makePayment($pending, ['method' => PaymentMethod::Qris->value]);

        foreach ([
            '/dashboard',
            '/cart',
            '/checkout',
            '/orders',
            '/orders/'.$order->number,
            '/orders/'.$pending->number,
            '/orders/'.$pending->number.'/payment',
            '/addresses',
            '/profile',
            '/notifications',
        ] as $url) {
            $this->actingAs($customer->user)->get($url)->assertOk();
        }
    }

    public function test_checkout_form_sends_every_field_the_request_requires(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $this->makeAddress($customer);
        $cart = $this->makeCart($customer);
        $this->makeCartItem($cart, $product, quantity: 3);

        $response = $this->actingAs($customer->user)->get('/checkout');
        $response->assertOk();

        // CheckoutRequest is the source of truth: a missing key here means the form
        // would silently fail validation for real customers.
        $required = [
            'name', 'phone', 'email', 'shipping_method', 'address_id', 'payment_method',
        ];

        foreach ($required as $field) {
            $this->assertStringContainsString(
                'name="'.$field.'"',
                $response->getContent(),
                "Form checkout tidak mengirim field wajib: {$field}",
            );
        }
    }

    public function test_admin_pages_render(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makePaidOrder($this->makeOrder($customer));
        $this->makeOrderItem($order, $product);
        $this->makeOrderAddress($order);
        $production = $this->makeProduction($order, ['status' => ProductionStatus::PRINTING, 'progress' => 25]);
        $this->makeDesign($order);

        foreach ([
            '/admin/dashboard',
            '/admin/products',
            '/admin/products/create',
            '/admin/products/'.$product->id.'/edit',
            '/admin/categories',
            '/admin/materials',
            '/admin/finishings',
            '/admin/prices',
            '/admin/orders',
            '/admin/orders/'.$order->number,
            '/admin/payments',
            '/admin/payment-settings',
            '/admin/payment-sandbox',
            '/admin/designs',
            '/admin/production',
            '/admin/customers',
            '/admin/operators',
            '/admin/reports',
            '/admin/invoices',
            '/admin/invoices/'.$order->number,
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $this->assertNotNull($production->id);
    }

    public function test_operator_pages_render(): void
    {
        $operator = $this->makeOperator();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makePaidOrder($this->makeOrder($customer));
        $this->makeOrderItem($order, $product);
        $this->makeOrderAddress($order);
        $job = $this->makeProduction($order, [
            'operator_id' => $operator->id,
            'status' => ProductionStatus::PACKING,
            'progress' => 80,
            'deadline' => now()->subDay(),
            'assigned_at' => now()->subDay(),
        ]);

        $job->statusHistories()->create([
            'changed_by' => $operator->user_id,
            'old_status' => ProductionStatus::PRINTING,
            'new_status' => ProductionStatus::PACKING,
            'progress' => 80,
            'note' => 'Warna sudah sesuai contoh.',
        ]);

        $job->qualityChecks()->create([
            'checked_by' => $operator->user_id,
            'result' => 'FAIL',
            'notes' => 'Sudut kanan bergeser 2 mm.',
        ]);

        foreach ([
            '/operator/dashboard',
            '/operator/jobs',
            '/operator/jobs/'.$job->id,
        ] as $url) {
            $this->actingAs($operator->user)->get($url)->assertOk();
        }
    }

    public function test_admin_people_toggles_work(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();

        $customerStateBefore = (bool) $customer->user->is_active;
        $operatorStateBefore = (bool) $operator->user->is_active;

        $this->actingAs($admin)
            ->post(route('admin.customers.toggle', $customer->id))
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.operators.toggle', $operator->id))
            ->assertRedirect();

        $this->assertNotSame($customerStateBefore, (bool) $customer->user->fresh()->is_active);
        $this->assertNotSame($operatorStateBefore, (bool) $operator->user->fresh()->is_active);
    }

    public function test_admin_order_status_form_only_offers_allowed_transitions(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer);
        $this->makeOrderItem($order, $product);

        // A pending-payment order can only be cancelled by an admin; the form must
        // not offer a transition the service would reject.
        $this->actingAs($admin)
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('value="CANCELLED"', false)
            ->assertDontSee('value="IN_PRODUCTION"', false)
            ->assertDontSee('value="COMPLETED"', false);
    }

    public function test_admin_order_status_transition_follows_the_service_rules(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($customer);
        $this->makeOrderItem($order, $product);

        // The transition offered by the redesigned form is accepted.
        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->post(route('admin.orders.status', $order), ['status' => 'CANCELLED', 'note' => 'Pelanggan meminta pembatalan.'])
            ->assertRedirect(route('admin.orders.show', $order));

        $this->assertSame('CANCELLED', $order->fresh()->status->value);

        // A transition the service forbids is still rejected, even if the form is bypassed.
        $second = $this->makeOrder($customer);
        $this->makeOrderItem($second, $product);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $second))
            ->post(route('admin.orders.status', $second), ['status' => 'IN_PRODUCTION'])
            ->assertRedirect()
            ->assertSessionHas('errors');

        $this->assertSame('PENDING_PAYMENT', $second->fresh()->status->value);
    }

    public function test_catalog_filters_apply_without_validation_errors(): void
    {
        $category = Category::factory()->create(['name' => 'Suvenir', 'slug' => 'suvenir']);
        $this->makeProduct(price: 15000, attributes: ['name' => 'Mug Uji', 'slug' => 'mug-uji', 'category_id' => $category->id]);

        foreach ([
            '/products?search=mug',
            '/products?category=suvenir',
            '/products?sort=newest',
            '/products?sort=latest',
            '/products?sort=popular',
            '/products?sort=price_low',
            '/products?sort=price_high',
            '/products?min_price=1000&max_price=50000',
        ] as $url) {
            $this->get($url)->assertOk()->assertSessionHasNoErrors();
        }

        $this->get('/products?sort=not-a-sort')->assertSessionHasErrors('sort');
    }
}
