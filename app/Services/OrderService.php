<?php

namespace App\Services;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Notifications\OrderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OrderService
{
    public function __construct(private readonly PriceCalculationService $priceCalculator) {}

    /** @param array<string, mixed> $checkout */
    public function checkout(Customer $customer, array $checkout): Order
    {
        return DB::transaction(function () use ($customer, $checkout) {
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->with(['items.product', 'items.material', 'items.finishing'])
                ->lockForUpdate()
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw new RuntimeException('Keranjang belanja kosong.');
            }

            $calculations = $cart->items->map(function ($item) {
                $configuration = [
                    'quantity' => $item->quantity,
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                    'material_id' => $item->material_id,
                    'finishing_id' => $item->finishing_id,
                ];

                $calculation = $this->priceCalculator->calculate($item->product, $configuration);
                $item->update(['price_at_addition' => $calculation['total']]);

                return [$item, $calculation];
            });

            $subtotal = $calculations->sum(fn (array $row) => $row[1]['total']);
            $shippingFee = $checkout['shipping_method'] === 'delivery' ? (int) config('printing.delivery_fee') : 0;
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
                'shipping_method' => $checkout['shipping_method'],
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
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                    'material_id' => $item->material_id,
                    'material_name' => $item->material?->name,
                    'material_reference' => $item->material_id ? (string) $item->material_id : null,
                    'finishing_id' => $item->finishing_id,
                    'finishing_name' => $item->finishing?->name,
                    'finishing_reference' => $item->finishing_id ? (string) $item->finishing_id : null,
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

            $designVersion = 0;
            foreach ($orderItems as $orderItem) {
                $reference = $orderItem->design_reference;
                if (! $reference || ! Storage::disk('local')->exists($reference)) {
                    continue;
                }
                $designVersion++;
                $fullPath = Storage::disk('local')->path($reference);
                $order->designFiles()->create([
                    'order_item_id' => $orderItem->id,
                    'original_filename' => $orderItem->custom_parameters['design_original_filename'] ?? basename($reference),
                    'stored_filename' => basename($reference),
                    'path' => $reference,
                    'extension' => strtolower(pathinfo($reference, PATHINFO_EXTENSION)),
                    'mime_type' => mime_content_type($fullPath) ?: 'application/octet-stream',
                    'size' => filesize($fullPath) ?: 0,
                    'version' => $designVersion,
                    'status' => DesignStatus::Pending,
                    'uploaded_by' => $customer->user_id,
                    'uploaded_at' => now(),
                ]);
            }

            $order->address()->create([
                'recipient' => $checkout['recipient'],
                'phone' => $checkout['address_phone'],
                'address' => $checkout['address'],
                'district' => $checkout['district'],
                'city' => $checkout['city'],
                'province' => $checkout['province'],
                'postal_code' => $checkout['postal_code'],
                'country' => 'Indonesia',
                'shipping_method' => $checkout['shipping_method'],
            ]);

            $order->payment()->create([
                'status' => PaymentStatus::Unpaid,
                'method' => 'BANK_TRANSFER',
                'amount' => $order->grand_total,
                'bank_name' => config('printing.bank_name'),
                'account_number' => config('printing.bank_account_number'),
                'account_name' => config('printing.bank_account_name'),
            ]);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'changed_by' => $customer->user_id,
                'old_status' => null,
                'new_status' => OrderStatus::PendingPayment->value,
                'note' => 'Pesanan dibuat dari checkout.',
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
