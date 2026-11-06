<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentSettingTest extends TestCase
{
    private const SERVER_KEY = 'SB-Mid-server-abc123';

    private const CLIENT_KEY = 'SB-Mid-client-def456';

    private const MERCHANT_ID = 'G812785002';

    protected function setUp(): void
    {
        parent::setUp();

        // The environment must not leak into these assertions.
        config([
            'payments.server_key' => null,
            'payments.client_key' => null,
            'payments.merchant_id' => null,
            'payments.sandbox' => false,
        ]);
    }

    public function test_admin_can_open_the_settings_page(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payment-settings.edit'))
            ->assertOk()
            ->assertSee('Server Key')
            ->assertSee('Client Key')
            ->assertSee('Merchant ID');
    }

    public function test_sandbox_endpoint_code_wraps_in_narrow_layouts(): void
    {
        config(['payments.sandbox' => true]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payment-settings.edit'))
            ->assertOk()
            ->assertSee('<code class="break-all">', false);
    }

    public function test_non_admin_roles_cannot_reach_the_settings_page(): void
    {
        $customer = $this->makeCustomer();

        $this->actingAs($customer->user)->get(route('admin.payment-settings.edit'))->assertForbidden();
        $this->actingAs($this->makeOperator()->user)->put(route('admin.payment-settings.update'), [
            'server_key' => self::SERVER_KEY,
        ])->assertForbidden();
    }

    public function test_admin_can_store_the_three_credentials(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->put(route('admin.payment-settings.update'), [
                'server_key' => self::SERVER_KEY,
                'client_key' => self::CLIENT_KEY,
                'merchant_id' => self::MERCHANT_ID,
                'sandbox' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $record = PaymentSetting::query()->firstOrFail();

        $this->assertSame(self::SERVER_KEY, $record->server_key);
        $this->assertSame(self::CLIENT_KEY, $record->client_key);
        $this->assertSame(self::MERCHANT_ID, $record->merchant_id);
        $this->assertTrue($record->sandbox);
        $this->assertSame($admin->id, $record->updated_by);
    }

    /** The whole point of the encrypted cast: a raw dump must not expose a key. */
    public function test_credentials_are_encrypted_at_rest(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.payment-settings.update'), [
            'server_key' => self::SERVER_KEY,
            'client_key' => self::CLIENT_KEY,
            'merchant_id' => self::MERCHANT_ID,
        ]);

        $row = DB::table('payment_settings')->first();

        foreach (['server_key', 'client_key', 'merchant_id'] as $column) {
            $this->assertNotNull($row->{$column});
            $this->assertStringNotContainsString(
                self::SERVER_KEY,
                (string) $row->{$column},
                "{$column} leaked the server key into the database.",
            );
            $this->assertStringNotContainsString(self::CLIENT_KEY, (string) $row->{$column});
            $this->assertStringNotContainsString(self::MERCHANT_ID, (string) $row->{$column});
        }
    }

    /** A stored value must never be rendered back into the page. */
    public function test_stored_credentials_are_never_shown_again(): void
    {
        $this->actingAs($this->makeAdmin())->put(route('admin.payment-settings.update'), [
            'server_key' => self::SERVER_KEY,
            'client_key' => self::CLIENT_KEY,
            'merchant_id' => self::MERCHANT_ID,
        ]);

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payment-settings.edit'))
            ->assertOk()
            ->assertDontSee(self::SERVER_KEY, false)
            ->assertDontSee(self::CLIENT_KEY, false)
            ->assertDontSee(self::MERCHANT_ID, false)
            ->assertSee('Tersimpan', false)
            ->assertSee('biarkan kosong untuk mempertahankan nilai', false);
    }

    /** A blank field must keep the working credential instead of wiping it. */
    public function test_a_blank_field_keeps_the_stored_value(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.payment-settings.update'), [
            'server_key' => self::SERVER_KEY,
            'client_key' => self::CLIENT_KEY,
            'merchant_id' => self::MERCHANT_ID,
        ]);

        // The form is submitted again with only the sandbox flag toggled.
        $this->actingAs($admin)->put(route('admin.payment-settings.update'), [
            'server_key' => '',
            'client_key' => '',
            'merchant_id' => '',
            'sandbox' => '0',
        ])->assertRedirect();

        $record = PaymentSetting::query()->firstOrFail();

        $this->assertSame(self::SERVER_KEY, $record->server_key);
        $this->assertSame(self::CLIENT_KEY, $record->client_key);
        $this->assertSame(self::MERCHANT_ID, $record->merchant_id);
        $this->assertFalse($record->sandbox);
    }

    public function test_malformed_credentials_are_rejected(): void
    {
        $this->actingAs($this->makeAdmin())
            ->put(route('admin.payment-settings.update'), ['server_key' => 'bad key with spaces'])
            ->assertSessionHasErrors('server_key');

        $this->actingAs($this->makeAdmin())
            ->put(route('admin.payment-settings.update'), ['merchant_id' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('merchant_id');

        $this->assertSame(0, PaymentSetting::query()->count());
    }

    public function test_credentials_can_be_cleared_and_fall_back_to_the_environment(): void
    {
        config(['payments.server_key' => 'env-server-key']);

        $admin = $this->makeAdmin();

        $this->actingAs($admin)->put(route('admin.payment-settings.update'), [
            'server_key' => self::SERVER_KEY,
        ]);

        $this->assertSame(self::SERVER_KEY, app(PaymentSettings::class)->serverKey());

        $this->actingAs($admin)
            ->delete(route('admin.payment-settings.destroy'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = app(PaymentSettings::class);
        $this->assertNull($settings->record()?->server_key);
        // Falls back to the environment instead of leaving the gateway unconfigured.
        $this->assertSame('env-server-key', $settings->serverKey());
    }

    /** The dashboard value must win over the environment for the live gateway. */
    public function test_gateway_uses_the_dashboard_credentials(): void
    {
        config([
            'payments.server_key' => 'env-server-key',
            'payments.sandbox' => true,
        ]);

        $this->actingAs($this->makeAdmin())->put(route('admin.payment-settings.update'), [
            'server_key' => self::SERVER_KEY,
            'sandbox' => '1',
        ]);

        $gateway = app(MidtransGateway::class);

        $this->assertTrue($gateway->isConfigured());
        $this->assertSame('https://api.sandbox.midtrans.com', $gateway->baseUrl());
    }

    public function test_sandbox_toggle_from_the_dashboard_switches_the_environment(): void
    {
        config(['payments.sandbox' => true]);

        $this->actingAs($this->makeAdmin())->put(route('admin.payment-settings.update'), ['sandbox' => '0']);

        $this->assertSame('https://api.midtrans.com', app(MidtransGateway::class)->baseUrl());
    }

    public function test_settings_page_does_not_break_the_existing_payments_list(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());
        $this->makePaidOrder($order);

        $this->actingAs($admin)->get(route('admin.payment-settings.edit'))->assertOk();
        $this->actingAs($admin)->get(route('admin.payments.index'))->assertOk()->assertSee($order->number);
    }

    /**
     * A rotated APP_KEY makes existing encrypted credentials undecryptable.
     * The page must stay reachable and the gateway must fall back to the
     * environment values instead of throwing a 500 for every request.
     */
    public function test_credentials_encrypted_with_an_old_app_key_fall_back_to_environment(): void
    {
        config([
            'payments.server_key' => 'env-server-key',
            'payments.client_key' => 'env-client-key',
            'payments.merchant_id' => 'env-merchant-id',
            'payments.sandbox' => true,
        ]);

        DB::table('payment_settings')->insert([
            'driver' => 'midtrans',
            'server_key' => 'eyJpdiI6Im9sZC1rZXkiLCJ2YWx1ZSI6IiJ9',
            'client_key' => 'not-a-valid-encrypted-payload',
            'merchant_id' => 'still-encrypted-with-the-old-key',
            'sandbox' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $settings = app(PaymentSettings::class);

        $this->assertNull($settings->record()?->server_key);
        $this->assertSame('env-server-key', $settings->serverKey());
        $this->assertSame('env-client-key', $settings->clientKey());
        $this->assertSame('env-merchant-id', $settings->merchantId());
        $this->assertSame('environment', $settings->sources()['server_key']);
        $this->assertTrue(app(MidtransGateway::class)->isConfigured());

        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payment-settings.edit'))
            ->assertOk()
            ->assertDontSee('still-encrypted-with-the-old-key', false);
    }
}
