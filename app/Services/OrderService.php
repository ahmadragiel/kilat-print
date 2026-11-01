<?php

namespace App\Services;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderNotification;
use App\Support\DeliveryFee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Turns a customer cart into an order that is waiting for payment.
 *
 * Checkout never marks a payment as paid. It snapshots the cart into the order,
 * stores the design the customer produced, and opens a PENDING payment. The
 * order only becomes payable/confirmed through the payment gateway flow.
 */
class OrderService
{
    public function __construct(private readonly PriceCalculationService $priceCalculator) {}

    /** @param  array<string, mixed>  $checkout  Validated checkout payload. */
    public function checkout(Customer $customer, array $checkout): Order
    {
        return DB::transaction(function () use ($customer, $checkout) {
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->with(['items.product'])
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new RuntimeException('Keranjang belanja kosong.');
            }

            if (($checkout['shipping_method'] ?? null) !== 'delivery') {
                throw ValidationException::withMessages([
                    'shipping_method' => 'Pengambilan di lokasi sudah tidak tersedia. Pesanan hanya dapat dikirim ke alamat.',
                ]);
            }

            $address = Address::query()
                ->where('customer_id', $customer->id)
                ->find($checkout['address_id'] ?? null);

            if (! $address) {
                throw ValidationException::withMessages([
                    'address_id' => 'Pilih alamat pengiriman yang tersimpan dan milik akun Anda.',
                ]);
            }

            $calculations = $cart->items->map(function ($item) {
                $configuration = [
                    'quantity' => $item->quantity,
                    'variant' => $item->variant,
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                ];

                $calculation = $this->priceCalculator->calculate($item->product, $configuration);
                $item->update(['price_at_addition' => $calculation['total']]);

                return [$item, $calculation];
            });

            $subtotal = $calculations->sum(fn (array $row) => $row[1]['total']);
            $shippingFee = DeliveryFee::amount();

            // The order starts waiting for payment. It is promoted to
            // PAYMENT_CONFIRMED only after the provider confirms the transaction,
            // and only then does a production record exist.
            $order = Order::create([
                'customer_id' => $customer->id,
                'customer_name' => $checkout['name'],
                'customer_phone' => $checkout['phone'],
                'customer_email' => $checkout['email'],
                'number' => $this->uniqueOrderNumber(),
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'grand_total' => $subtotal + $shippingFee,
                'shipping_method' => 'delivery',
                'paid_at' => null,
                'deadline' => now()->addDays(max(1, (int) $cart->items->max(fn ($item) => $item->product->production_days))),
            ]);

            $orderItems = $order->items()->createMany($calculations->map(function (array $row) {
                [$item, $calculation] = $row;
                $product = $item->product;

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_reference' => (string) $product->id,
                    'product_slug' => $product->slug,
                    'quantity' => $item->quantity,
                    'size' => $item->size,
                    'variant' => $calculation['variant'] ?? $item->variant,
                    'variant_label' => $calculation['variant_label'] ?? $item->variant_label,
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                    'color' => $item->color,
                    'production_method' => $item->production_method,
                    'custom_parameters' => $item->custom_parameters,
                    'configuration' => array_filter([
                        'specification' => $item->custom_parameters,
                        'design' => data_get($item->custom_parameters, 'design_configuration'),
                        'design_draft_id' => $item->custom_design_draft_id,
                        'design_version' => data_get($item->custom_parameters, 'design_version'),
                    ], fn ($value) => $value !== null && $value !== []),
                    'custom_design_draft_id' => $item->custom_design_draft_id,
                    'design_reference' => $item->design_reference,
                    'notes' => $item->notes,
                    'unit_price' => $calculation['unit_price'],
                    'line_total' => $calculation['total'],
                ];
            })->all());

            $this->attachDesignFiles($order, $orderItems, $customer);

            $order->address()->create([
                'label' => $address->label,
                'recipient' => $address->recipient,
                'phone' => $address->phone,
                'address' => $address->address,
                'address_line2' => $address->address_line2,
                'district' => $address->district,
                'city' => $address->city,
                'province' => $address->province,
                'postal_code' => $address->postal_code,
                'country' => $address->country,
                'shipping_method' => 'delivery',
                'notes' => $checkout['shipping_notes'] ?? null,
            ]);

            // A PENDING payment only: the customer pays through QRIS or bank
            // transfer, and the provider confirms it. No proof, no admin verify.
            $order->payment()->create([
                'status' => PaymentStatus::Pending,
                'method' => PaymentMethod::from($checkout['payment_method'])->value,
                'amount' => $order->grand_total,
                'expires_at' => now()->addHours(max(1, (int) config('payments.expiry_hours', 24))),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'changed_by' => $customer->user_id,
                'old_status' => null,
                'new_status' => OrderStatus::PendingPayment->value,
                'note' => 'Pesanan dibuat dari checkout. Menunggu konfirmasi pembayaran dari penyedia pembayaran.',
            ]);

            $cart->items()->delete();
            $customer->user->notify(new OrderNotification(
                'Pesanan berhasil dibuat',
                "Pesanan {$order->number} menunggu pembayaran.",
                route('customer.orders.show', $order),
            ));

            return $order->load(['items', 'address', 'payment', 'statusHistories']);
        }, 3);
    }

    /**
     * Copy the design the customer produced into the order.
     *
     * There is no admin review stage: a stored design is immediately usable by
     * production once the order is paid.
     *
     * @param  Collection<int, OrderItem>  $orderItems
     */
    private function attachDesignFiles(Order $order, $orderItems, Customer $customer): void
    {
        $version = 0;

        foreach ($orderItems as $orderItem) {
            $reference = $orderItem->design_reference;
            if (! $reference || ! Storage::disk('local')->exists($reference)) {
                continue;
            }

            $version++;
            $fullPath = Storage::disk('local')->path($reference);
            $order->designFiles()->create([
                'order_item_id' => $orderItem->id,
                'original_filename' => $orderItem->custom_parameters['design_original_filename'] ?? basename($reference),
                'stored_filename' => basename($reference),
                'path' => $reference,
                'extension' => strtolower(pathinfo($reference, PATHINFO_EXTENSION)),
                'mime_type' => mime_content_type($fullPath) ?: 'application/octet-stream',
                'size' => filesize($fullPath) ?: 0,
                'version' => $version,
                'status' => DesignStatus::Approved,
                'uploaded_by' => $customer->user_id,
                'uploaded_at' => now(),
            ]);
        }
    }

    private function uniqueOrderNumber(): string
    {
        $date = now()->format('Ymd');

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $sequence = (Order::query()->whereDate('created_at', today())->count() + 1) + $attempt;
            $number = 'KP-'.$date.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            if (! Order::where('number', $number)->exists()) {
                return $number;
            }
        }

        return 'KP-'.$date.'-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
    }
}
