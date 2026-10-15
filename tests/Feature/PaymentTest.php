<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Http\Requests\CheckoutRequest;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Fakes\FakeMidtransApi;
use Tests\TestCase;

/**
 * Guards the removal of the manual proof and verification workflow.
 *
 * The settlement rules themselves live in {@see PaymentGatewayTest}.
 */
class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_customer_checkout_validation_knows_nothing_about_proofs(): void
    {
        $rules = (new CheckoutRequest)->rules();

        $this->assertArrayHasKey('payment_method', $rules);
        foreach (array_keys($rules) as $field) {
            $this->assertStringNotContainsString('proof', $field);
            $this->assertStringNotContainsString('material', $field);
            $this->assertStringNotContainsString('finishing', $field);
        }
    }

    public function test_no_customer_route_uploads_a_payment_proof(): void
    {
        $routes = app('router')->getRoutes();

        $this->assertNull($routes->getByName('customer.orders.proof'));
        $this->assertNull($routes->getByName('customer.orders.payment-proof'));

        foreach ($routes as $route) {
            $this->assertStringNotContainsString('payment-proof', (string) $route->uri());
        }
    }

    public function test_admin_has_no_manual_verification_actions_for_payments(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->makePaidOrder($order);

        $routes = app('router')->getRoutes();

        $this->assertNull($routes->getByName('admin.payments.verify'));
        $this->assertNull($routes->getByName('admin.payments.reject'));

        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk();
    }

    public function test_customer_order_page_has_no_verification_controls(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->makePayment($order);

        $this->actingAs($customer->user)
            ->get(route('customer.orders.show', $order))
            ->assertOk()
            ->assertDontSee('Unggah bukti pembayaran', false)
            ->assertDontSee('Verifikasi pembayaran', false)
            ->assertDontSee('Setujui pembayaran', false)
            ->assertDontSee('Tolak pembayaran', false);
    }

    public function test_legacy_payment_proof_stays_downloadable_for_admins(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $oldPath = 'payment-proofs/'.$order->id.'/legacy.png';
        Storage::disk('local')->put($oldPath, 'legacy proof');
        $this->makePayment($order, [
            'status' => PaymentStatus::Paid,
            'proof_path' => $oldPath,
            'proof_original_filename' => 'legacy.png',
        ]);

        $this->app['auth']->logout();
        $this->get(route('admin.payments.proof', $order))->assertRedirect(route('login'));
        $this->actingAs($customer->user)
            ->get(route('admin.payments.proof', $order))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.payments.proof', $order))
            ->assertDownload('legacy.png');
    }

    public function test_gateway_secret_is_never_exposed_to_the_browser(): void
    {
        $gateway = new FakeMidtransApi;
        $gateway->install();

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->makePayment($order, ['status' => PaymentStatus::Pending]);

        $customerPage = $this->actingAs($customer->user)->get(route('customer.orders.show', $order));
        $customerPage->assertOk()->assertDontSee(FakeMidtransApi::SERVER_KEY, false);

        $paymentPage = $this->actingAs($customer->user)->get(route('customer.orders.payment', $order));
        $paymentPage->assertOk()->assertDontSee(FakeMidtransApi::SERVER_KEY, false);
    }
}
