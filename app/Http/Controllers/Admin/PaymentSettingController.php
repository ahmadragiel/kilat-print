<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Services\Payment\Drivers\MidtransGateway;
use App\Services\Payment\PaymentSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lets an admin provision the payment gateway from the dashboard.
 *
 * The credential fields are write-only: a stored value is never rendered back
 * into the form, and the response only reports whether each value exists. Keys
 * are encrypted by the model cast, and this controller deliberately never logs
 * or echoes the submitted values.
 */
class PaymentSettingController extends Controller
{
    public function __construct(
        private readonly PaymentSettings $settings,
        private readonly MidtransGateway $gateway,
    ) {}

    public function edit(Request $request): View
    {
        $record = $this->settings->record();
        $sources = $this->settings->sources();

        return view('admin.payment-settings', [
            'record' => $record,
            'sources' => $sources,
            'driver' => $this->settings->driver(),
            'sandbox' => $this->settings->sandbox(),
            'supportedBanks' => MidtransGateway::SUPPORTED_BANKS,
            'bank' => (string) config('payments.bank_transfer_bank', 'bca'),
            'currency' => (string) config('payments.currency', 'IDR'),
            'expiryHours' => (int) config('payments.expiry_hours', 24),
            'webhookPath' => route('payments.webhook.midtrans', absolute: false),
            'baseUrl' => $this->gateway->baseUrl(),
            'isConfigured' => $this->gateway->isConfigured(),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // A blank field keeps the stored value, so a half filled form never
            // wipes a working credential.
            'server_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'client_key' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'merchant_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'sandbox' => ['nullable', 'boolean'],
        ], [
            'regex' => 'Format kredensial tidak valid. Midtrans hanya memakai huruf, angka, tanda hubung, dan garis bawah.',
        ]);

        $record = PaymentSetting::query()->firstOrNew([]);
        $record->fill([
            'driver' => 'midtrans',
            'sandbox' => $request->boolean('sandbox'),
            'updated_by' => $request->user()->id,
        ]);

        // Only overwrite a field when a new value was actually submitted.
        foreach (['server_key', 'client_key', 'merchant_id'] as $field) {
            if (filled($validated[$field] ?? null)) {
                $record->{$field} = $validated[$field];
            }
        }

        $record->save();

        // A previous request in this process may already have resolved the old
        // values; drop them so the gateway immediately uses the new ones.
        $this->settings->flush();
        app()->forgetInstance(PaymentSettings::class);

        // Only field names are logged. Credential values never reach the log.
        Log::info('Payment gateway settings updated.', [
            'user_id' => $request->user()->id,
            'fields' => array_keys(array_filter(
                $validated,
                fn ($value) => filled($value),
            )),
        ]);

        return back()->with('success', 'Konfigurasi penyedia pembayaran disimpan.');
    }

    /**
     * Remove the stored credentials and fall back to the environment.
     *
     * The values are nulled rather than logged or echoed, and any pending
     * transaction keeps its stored reference so history stays readable.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $record = PaymentSetting::query()->first();

        if ($record) {
            $record->forceFill([
                'server_key' => null,
                'client_key' => null,
                'merchant_id' => null,
                'updated_by' => $request->user()->id,
            ])->save();
        }

        $this->settings->flush();
        app()->forgetInstance(PaymentSettings::class);

        Log::info('Payment gateway credentials cleared.', ['user_id' => $request->user()->id]);

        return back()->with('success', 'Kredensial dari dashboard dihapus. Konfigurasi .env kembali digunakan.');
    }
}
