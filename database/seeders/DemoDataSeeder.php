<?php

namespace Database\Seeders;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\DesignFile;
use App\Models\Notification as DatabaseNotification;
use App\Models\Operator;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductionAssignment;
use App\Models\ProductionOrder;
use App\Models\ProductionPhoto;
use App\Models\ProductionStatusHistory;
use App\Models\QualityCheck;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo transaction history for local development and automated tests.
 *
 * The catalog itself is owned by RealCatalogSeeder. This seeder only adds
 * accounts, a cart, orders, production records, and notifications so the
 * dashboards have something to display. It is skipped in production and never
 * creates demo products.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoDataSeeder dilewati di production. Jalankan RealCatalogSeeder untuk katalog nyata.');

            return;
        }

        $this->call([RealCatalogSeeder::class, DemoAccountSeeder::class]);

        $accounts = [
            'admin' => User::where('email', 'admin@kilatprint.test')->firstOrFail(),
            'operator' => User::where('email', 'operator@kilatprint.test')->firstOrFail(),
            'customer' => User::where('email', 'customer@kilatprint.test')->firstOrFail(),
        ];

        $this->seedCustomerProfile($accounts['customer']);
        $this->seedCart($accounts['customer'], $this->product('gelas-mug-custom'));
        $this->seedOrders($accounts);
        $this->seedNotifications($accounts['customer']);
    }

    private function product(string $catalogKey): Product
    {
        return Product::withTrashed()->where('catalog_key', $catalogKey)->firstOrFail();
    }

    private function seedCustomerProfile(User $customerUser): void
    {
        $customer = Customer::where('user_id', $customerUser->id)->firstOrFail();

        Address::updateOrCreate(
            ['customer_id' => $customer->id, 'label' => 'Demo Office'],
            [
                'recipient' => $customerUser->name,
                'phone' => '081200000002',
                'address' => 'Jalan Demo Creative No. 1',
                'district' => 'Pesanggrahan',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12290',
                'country' => 'Indonesia',
                'is_primary' => true,
                'notes' => 'Demo address for local dashboard and checkout testing.',
            ],
        );
    }

    private function seedCart(User $customerUser, Product $product): void
    {
        $customer = Customer::where('user_id', $customerUser->id)->firstOrFail();
        $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);
        $quantity = 2;

        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id, 'design_reference' => null],
            [
                'quantity' => $quantity,
                'size' => null,
                'variant' => null,
                'variant_label' => null,
                'length_cm' => null,
                'width_cm' => null,
                'material_id' => null,
                'finishing_id' => null,
                'color' => 'Full Color',
                'production_method' => 'digital',
                'custom_parameters' => ['demo' => true, 'notes' => 'Demo cart configuration.'],
                'price_at_addition' => $quantity * (float) $product->base_price,
                'notes' => 'Demo cart item; price is recalculated by the server at checkout.',
            ],
        );
    }

    /**
     * @param  array{admin: User, operator: User, customer: User}  $accounts
     */
    private function seedOrders(array $accounts): void
    {
        $customer = Customer::where('user_id', $accounts['customer']->id)->firstOrFail();
        $operator = Operator::where('user_id', $accounts['operator']->id)->firstOrFail();

        $mug = $this->product('gelas-mug-custom');
        $paperBag = $this->product('paper-bag-custom');
        $kertasKado = $this->product('kertas-kado-custom');
        $topi = $this->product('topi-ultah-custom');

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $mug,
            'KP-DEMO-0001',
            OrderStatus::PENDING_PAYMENT,
            PaymentStatus::Pending,
            2,
            false,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $topi,
            'KP-DEMO-0002',
            OrderStatus::PENDING_PAYMENT,
            PaymentStatus::Expired,
            3,
            false,
            variant: 'polkadot',
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $kertasKado,
            'KP-DEMO-0003',
            OrderStatus::PAYMENT_CONFIRMED,
            PaymentStatus::Paid,
            50,
            true,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $paperBag,
            'KP-DEMO-0004',
            OrderStatus::IN_PRODUCTION,
            PaymentStatus::Paid,
            100,
            true,
            ProductionStatus::Printing,
            variant: 'large',
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $mug,
            'KP-DEMO-0005',
            OrderStatus::COMPLETED,
            PaymentStatus::Paid,
            12,
            true,
            ProductionStatus::Completed,
            true,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $topi,
            'KP-DEMO-0006',
            OrderStatus::PAYMENT_CONFIRMED,
            PaymentStatus::Paid,
            6,
            true,
            variant: 'love',
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $paperBag,
            'KP-DEMO-0007',
            OrderStatus::IN_PRODUCTION,
            PaymentStatus::Paid,
            15,
            true,
            ProductionStatus::Printing,
            variant: 'medium',
        );

        $reworkOrder = Order::where('number', 'KP-DEMO-0007')->first();
        if ($reworkOrder && ($reworkProduction = $reworkOrder->production)) {
            QualityCheck::updateOrCreate(
                ['production_order_id' => $reworkProduction->id, 'result' => 'FAIL'],
                [
                    'checker_id' => $accounts['operator']->id,
                    'notes' => 'Warna cetakan tidak sesuai; perlu re-print ulang.',
                    'checked_at' => now()->subHours(3),
                ],
            );
        }
    }

    private function seedOrder(
        array $accounts,
        Customer $customer,
        Operator $operator,
        Product $product,
        string $number,
        OrderStatus $orderStatus,
        PaymentStatus $paymentStatus,
        int $quantity,
        bool $withDesign,
        ?ProductionStatus $productionStatus = null,
        bool $withQualityCheck = false,
        ?string $variant = null,
    ): void {
        $variantLabel = $variant !== null ? $product->variantLabel($variant) : null;
        $unitPrice = $this->unitPrice($product, $variant);
        $lineTotal = $unitPrice * $quantity;

        $order = Order::updateOrCreate(
            ['number' => $number],
            [
                'customer_id' => $customer->id,
                'customer_name' => $accounts['customer']->name,
                'customer_phone' => '081200000002',
                'customer_email' => $accounts['customer']->email,
                'status' => $orderStatus,
                'subtotal' => $lineTotal,
                'shipping_fee' => 0,
                'grand_total' => $lineTotal,
                'shipping_method' => 'pickup',
                'internal_notes' => 'Demo order. The values and history are sample data for local dashboards.',
                'deadline' => now()->addDays(7),
                'paid_at' => $paymentStatus === PaymentStatus::PAID ? now()->subDays(2) : null,
                'completed_at' => $orderStatus === OrderStatus::COMPLETED ? now()->subDay() : null,
            ],
        );

        OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $product->id],
            [
                'product_name' => $product->name,
                'product_reference' => 'DEMO-'.$product->slug,
                'product_slug' => $product->slug,
                'quantity' => $quantity,
                'size' => null,
                'variant' => $variant,
                'variant_label' => $variantLabel,
                'length_cm' => null,
                'width_cm' => null,
                'color' => 'Full Color',
                'production_method' => 'digital',
                'custom_parameters' => ['demo' => true],
                'configuration' => ['source' => 'DemoDataSeeder', 'variant' => $variant],
                'design_reference' => null,
                'notes' => 'Demo configuration snapshot.',
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ],
        );

        OrderAddress::updateOrCreate(
            ['order_id' => $order->id],
            [
                'label' => 'Demo Office',
                'recipient' => $accounts['customer']->name,
                'phone' => '081200000002',
                'address' => 'Jalan Demo Creative No. 1',
                'district' => 'Pesanggrahan',
                'city' => 'Jakarta Selatan',
                'province' => 'DKI Jakarta',
                'postal_code' => '12290',
                'country' => 'Indonesia',
                'shipping_method' => 'pickup',
                'notes' => 'Demo order address snapshot.',
            ],
        );

        Payment::updateOrCreate(
            ['order_id' => $order->id],
            [
                'status' => $paymentStatus,
                'method' => PaymentMethod::BankTransfer->value,
                'amount' => $lineTotal,
                'provider' => 'midtrans',
                'provider_reference' => 'demo-'.$order->number,
                'provider_status' => $paymentStatus === PaymentStatus::Paid ? 'settlement' : $paymentStatus->value,
                'channel' => PaymentMethod::BankTransfer->value,
                'virtual_account_bank' => 'Bank Demo Indonesia',
                'virtual_account_number' => '8808'.str_pad((string) $order->id, 12, '0', STR_PAD_LEFT),
                'bank_name' => 'Bank Demo Indonesia',
                'account_name' => 'PT Solusi Print Cepat',
                'expires_at' => $paymentStatus === PaymentStatus::Pending ? now()->addDay() : now()->subDays(2),
                'paid_at' => $paymentStatus === PaymentStatus::Paid ? now()->subDays(2) : null,
                'failure_reason' => $paymentStatus === PaymentStatus::Expired
                    ? 'Waktu pembayaran habis sebelum transaksi dikonfirmasi.'
                    : null,
                'proof_path' => null,
                'proof_version' => 1,
                'submitted_at' => null,
                'verified_at' => $paymentStatus === PaymentStatus::Paid ? now()->subDays(2) : null,
                'verified_by' => null,
                'rejection_reason' => null,
            ],
        );

        $this->history($order, null, OrderStatus::PENDING_PAYMENT, $accounts['customer']->id, 'Demo order created.');
        if ($orderStatus !== OrderStatus::PENDING_PAYMENT) {
            $this->history($order, null, OrderStatus::PAYMENT_CONFIRMED, null, 'Demo payment confirmed by the payment provider.');
        }
        if (in_array($orderStatus, [OrderStatus::IN_PRODUCTION, OrderStatus::COMPLETED], true)) {
            $this->history($order, OrderStatus::PAYMENT_CONFIRMED, OrderStatus::IN_PRODUCTION, $accounts['admin']->id, 'Demo production task assigned.');
        }
        if ($orderStatus === OrderStatus::COMPLETED) {
            $this->history($order, OrderStatus::IN_PRODUCTION, OrderStatus::COMPLETED, $accounts['operator']->id, 'Demo quality control passed.');
        }

        if ($withDesign) {
            DesignFile::updateOrCreate(
                ['order_id' => $order->id, 'version' => 1],
                [
                    'order_item_id' => $order->items()->value('id'),
                    'original_filename' => 'demo-design.pdf',
                    'stored_filename' => 'demo/designs/'.$order->number.'/v1.pdf',
                    'path' => 'demo/designs/'.$order->number.'/v1.pdf',
                    'extension' => 'pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 245760,
                    'status' => DesignStatus::Approved,
                    'notes' => 'Demo design metadata; no binary file is required for local seeding.',
                    'uploaded_by' => $accounts['customer']->id,
                    'uploaded_at' => now()->subDays(2),
                ],
            );
        }

        // A production record only exists once the payment provider has confirmed the
        // order, which is exactly what the settlement service does at runtime.
        if ($orderStatus !== OrderStatus::PENDING_PAYMENT) {
            $production = ProductionOrder::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'operator_id' => $productionStatus !== null ? $operator->id : null,
                    'status' => $productionStatus ?? ProductionStatus::InDesign,
                    'progress' => match ($productionStatus) {
                        ProductionStatus::Completed => 100,
                        null => 0,
                        default => 45,
                    },
                    'notes' => 'Demo production record. This is sample data for dashboard testing.',
                    'deadline' => now()->addDays(5),
                    'assigned_at' => $productionStatus !== null ? now()->subDays(1) : null,
                    'started_at' => $productionStatus !== null ? now()->subDays(1) : null,
                    'finished_at' => $productionStatus === ProductionStatus::Completed ? now()->subHours(4) : null,
                    'completed_at' => $productionStatus === ProductionStatus::Completed ? now()->subHours(4) : null,
                ],
            );

            if ($productionStatus === null) {
                // Paid but not yet scheduled: the queue is created, no operator.
                return;
            }

            ProductionAssignment::updateOrCreate(
                ['production_order_id' => $production->id, 'operator_id' => $operator->id],
                [
                    'assigned_by' => $accounts['admin']->id,
                    'assigned_at' => now()->subDays(1),
                    'unassigned_at' => null,
                    'is_current' => true,
                    'notes' => 'Demo assignment history.',
                ],
            );

            ProductionStatusHistory::firstOrCreate(
                ['production_order_id' => $production->id, 'new_status' => ProductionStatus::Printing->value],
                [
                    'changed_by' => $accounts['operator']->id,
                    'old_status' => ProductionStatus::InDesign->value,
                    'progress' => 25,
                    'note' => 'Demo printing started.',
                ],
            );

            if ($productionStatus === ProductionStatus::Completed) {
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::Finishing->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::Printing->value,
                        'progress' => 60,
                        'note' => 'Demo finishing completed.',
                    ],
                );
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::Packing->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::Finishing->value,
                        'progress' => 80,
                        'note' => 'Demo packing completed.',
                    ],
                );
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::QualityControl->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::Packing->value,
                        'progress' => 90,
                        'note' => 'Demo quality check submitted.',
                    ],
                );
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::Completed->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::QualityControl->value,
                        'progress' => 100,
                        'note' => 'Demo quality check passed.',
                    ],
                );
            }

            if ($withQualityCheck) {
                QualityCheck::updateOrCreate(
                    ['production_order_id' => $production->id, 'result' => 'PASS'],
                    [
                        'checker_id' => $accounts['operator']->id,
                        'notes' => 'Demo quality check passed.',
                        'checked_at' => now()->subHours(4),
                    ],
                );

                ProductionPhoto::updateOrCreate(
                    ['production_order_id' => $production->id, 'original_filename' => 'demo-progress.jpg'],
                    [
                        'uploaded_by' => $accounts['operator']->id,
                        'path' => 'demo/production/'.$order->number.'/progress.jpg',
                        'mime_type' => 'image/jpeg',
                        'size' => 182400,
                        'notes' => 'Demo production photo metadata; no binary file is required.',
                        'uploaded_at' => now()->subHours(5),
                    ],
                );
            }
        }
    }

    private function unitPrice(Product $product, ?string $variant): int
    {
        $query = $product->priceRules()->where('active', true)->where('is_wholesale', false);

        $price = null;

        if ($variant !== null) {
            $price = (clone $query)->where('variant_key', $variant)->orderByDesc('min_quantity')->value('price');
        }

        $price ??= (clone $query)->whereNull('variant_key')->orderByDesc('min_quantity')->value('price');

        return (int) ($price ?? $product->base_price);
    }

    private function history(Order $order, ?OrderStatus $old, OrderStatus $new, ?int $changedBy, string $note): void
    {
        OrderStatusHistory::updateOrCreate(
            ['order_id' => $order->id, 'new_status' => $new->value],
            [
                'changed_by' => $changedBy,
                'old_status' => $old?->value,
                'note' => $note,
            ],
        );
    }

    private function seedNotifications(User $customer): void
    {
        $notifications = [
            [
                'uuid' => '00000000-0000-4000-8000-000000000001',
                'type' => 'App\\Notifications\\OrderNotification',
                'data' => ['title' => 'Demo Kilat Print', 'message' => 'Selamat datang di dashboard demo Kilat Print.', 'url' => '/dashboard'],
            ],
            [
                'uuid' => '00000000-0000-4000-8000-000000000002',
                'type' => 'App\\Notifications\\OrderNotification',
                'data' => ['title' => 'Demo order tersedia', 'message' => 'Ada pesanan demo yang menunggu verifikasi pembayaran.', 'url' => '/orders'],
            ],
        ];

        foreach ($notifications as $notification) {
            DatabaseNotification::updateOrCreate(
                ['id' => $notification['uuid']],
                [
                    'type' => $notification['type'],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $customer->id,
                    'data' => $notification['data'],
                    'read_at' => null,
                ],
            );
        }
    }
}
