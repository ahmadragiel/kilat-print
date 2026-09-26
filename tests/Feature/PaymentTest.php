<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Notifications\OrderNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_customer_uploads_private_payment_proof_and_moves_order_to_review(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $payment = $this->makePayment($order);

        $response = $this->actingAs($customer->user)->post(route('customer.orders.payment', $order), [
            'proof' => UploadedFile::fake()->create('transfer.pdf', 30, 'application/pdf'),
        ]);

        $response->assertSessionHas('success');
        $payment->refresh();
        $this->assertSame(PaymentStatus::WAITING_VERIFICATION, $payment->status);
        $this->assertSame(OrderStatus::PAYMENT_REVIEW, $order->fresh()->status);
        $this->assertSame(1, $payment->proof_version);
        $this->assertNotNull($payment->proof_path);
        Storage::disk('local')->assertExists($payment->proof_path);
        Storage::disk('public')->assertMissing($payment->proof_path);
        $this->assertSame('transfer.pdf', $payment->proof_original_filename);

        $this->app['auth']->logout();
        $this->get(route('admin.payments.proof', $order))->assertRedirect(route('login'));
        $this->actingAs($customer->user)
            ->get(route('admin.payments.proof', $order))
            ->assertForbidden();
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.payments.proof', $order))
            ->assertDownload('transfer.pdf');
    }

    public function test_admin_approves_waiting_payment_and_records_verification_and_notification(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::PAYMENT_REVIEW]);
        $payment = $this->makePayment($order, [
            'status' => PaymentStatus::WAITING_VERIFICATION,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.payments.verify', $order));

        $response->assertSessionHas('success');
        $payment->refresh();
        $this->assertSame(PaymentStatus::PAID, $payment->status);
        $this->assertSame($admin->id, $payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame(OrderStatus::PAYMENT_CONFIRMED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
        Notification::assertSentTo($customer->user, OrderNotification::class);
    }

    public function test_admin_rejection_requires_a_reason_and_customer_can_reupload_a_new_private_version(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::PAYMENT_REVIEW]);
        $oldPath = 'payment-proofs/'.$order->id.'/old.png';
        Storage::disk('local')->put($oldPath, 'old proof');
        $payment = $this->makePayment($order, [
            'status' => PaymentStatus::WAITING_VERIFICATION,
            'proof_path' => $oldPath,
            'proof_original_filename' => 'old.png',
            'proof_version' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.reject', $order), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertSame(PaymentStatus::WAITING_VERIFICATION, $payment->fresh()->status);

        $this->actingAs($admin)
            ->post(route('admin.payments.reject', $order), ['reason' => 'Transfer value does not match.'])
            ->assertSessionHas('success');
        $this->assertSame(PaymentStatus::REJECTED, $payment->fresh()->status);
        $this->assertSame('Transfer value does not match.', $payment->fresh()->rejection_reason);
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->fresh()->status);

        $this->actingAs($customer->user)
            ->post(route('customer.orders.payment', $order), [
                'proof' => UploadedFile::fake()->create('replacement.pdf', 30, 'application/pdf'),
            ])
            ->assertSessionHas('success');
        $payment->refresh();
        $this->assertSame(PaymentStatus::WAITING_VERIFICATION, $payment->status);
        $this->assertSame(2, $payment->proof_version);
        $this->assertSame(OrderStatus::PAYMENT_REVIEW, $order->fresh()->status);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($payment->proof_path);
    }

    public function test_customer_cannot_use_admin_payment_actions(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::PAYMENT_REVIEW]);
        $this->makePayment($order, ['status' => PaymentStatus::WAITING_VERIFICATION]);

        $this->actingAs($customer->user)
            ->post(route('admin.payments.verify', $order))
            ->assertForbidden();
        $this->actingAs($customer->user)
            ->post(route('admin.payments.reject', $order), ['reason' => 'No'])
            ->assertForbidden();
    }
}
