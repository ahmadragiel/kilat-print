<?php

namespace Database\Seeders;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PricingType;
use App\Enums\ProductionStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DesignFile;
use App\Models\Finishing;
use App\Models\Material;
use App\Models\Notification as DatabaseNotification;
use App\Models\Operator;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionAssignment;
use App\Models\ProductionOrder;
use App\Models\ProductionPhoto;
use App\Models\ProductionStatusHistory;
use App\Models\QualityCheck;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = $this->seedAccounts();
        $catalog = $this->seedCatalog();
        $this->seedCustomerProfile($accounts['customer']);
        $this->seedCart($accounts['customer'], $catalog['products']['banner']);
        $this->seedOrders($accounts, $catalog);
        $this->seedNotifications($accounts['customer']);
    }

    /**
     * @return array{admin: User, operator: User, customer: User}
     */
    private function seedAccounts(): array
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@kilatprint.test'],
            [
                'name' => 'Admin Demo Kilat Print',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ],
        );

        $operatorUser = User::updateOrCreate(
            ['email' => 'operator@kilatprint.test'],
            [
                'name' => 'Operator Demo Kilat Print',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::OPERATOR,
                'is_active' => true,
            ],
        );

        $customerUser = User::updateOrCreate(
            ['email' => 'customer@kilatprint.test'],
            [
                'name' => 'Customer Demo Kilat Print',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::CUSTOMER,
                'is_active' => true,
            ],
        );

        Operator::updateOrCreate(
            ['user_id' => $operatorUser->id],
            [
                'employee_code' => 'OP-DEMO-001',
                'phone' => '081200000001',
                'specialization' => 'Digital printing and finishing',
                'notes' => 'Demo operator account for the Kilat Print dashboard.',
                'is_active' => true,
            ],
        );

        Customer::updateOrCreate(
            ['user_id' => $customerUser->id],
            [
                'customer_code' => 'CUST-DEMO-001',
                'phone' => '081200000002',
                'company' => 'Demo Creative Studio',
                'notes' => 'Demo customer profile; replace this data before production use.',
                'is_active' => true,
            ],
        );

        return [
            'admin' => $admin,
            'operator' => $operatorUser,
            'customer' => $customerUser,
        ];
    }

    /**
     * @return array{categories: array<string, Category>, materials: array<string, Material>, finishings: array<string, Finishing>, products: array<string, Product>}
     */
    private function seedCatalog(): array
    {
        $categoryDefinitions = [
            'banner' => ['Banner & Spanduk', 'banner'],
            'brochure' => ['Brochure & Flyer', 'brochure-flyer'],
            'poster' => ['Poster & Sticker', 'poster-sticker'],
            'stationery' => ['Stationery', 'stationery'],
            'invitation' => ['Invitation & Event', 'invitation-event'],
        ];

        $categories = [];
        foreach ($categoryDefinitions as $key => [$name, $slug]) {
            $categories[$key] = Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => 'Demo master data for '.$name.'. This catalog record is for local demonstration only.',
                    'image' => null,
                    'status' => 'active',
                ],
            );
        }

        $materialDefinitions = [
            'flexi280' => ['Flexi 280gr', PricingType::PER_SQM, 25000, 'Demo banner material.'],
            'flexi340' => ['Flexi 340gr', PricingType::PER_SQM, 30000, 'Demo premium banner material.'],
            'artpaper' => ['Art Paper 150gr', PricingType::PER_ITEM, 3500, 'Demo paper stock for flyers and cards.'],
            'cotton' => ['Cotton Premium', PricingType::PER_ITEM, 25000, 'Demo apparel material.'],
        ];

        $materials = [];
        foreach ($materialDefinitions as $key => [$name, $pricingType, $price, $description]) {
            $materials[$key] = Material::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description.' Demo data, not a real business quotation.',
                    'pricing_type' => $pricingType,
                    'price' => $price,
                    'status' => 'active',
                ],
            );
        }

        $finishingDefinitions = [
            'matte' => ['Matte', PricingType::PER_ITEM, 0, 'Demo matte finishing.'],
            'glossy' => ['Glossy', PricingType::PER_ITEM, 5000, 'Demo gloss finishing.'],
            'lamination' => ['Laminasi', PricingType::PER_SQM, 12000, 'Demo lamination finishing.'],
            'eyelet' => ['Mata Ayam', PricingType::PER_ITEM, 2000, 'Demo eyelet finishing.'],
        ];

        $finishings = [];
        foreach ($finishingDefinitions as $key => [$name, $pricingType, $price, $description]) {
            $finishings[$key] = Finishing::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description.' Demo data, not a real business quotation.',
                    'pricing_type' => $pricingType,
                    'price' => $price,
                    'status' => 'active',
                ],
            );
        }

        $productDefinitions = [
            'banner' => ['Banner', 'banner', 25000, PricingType::PER_SQM, 1, 3, ['flexi280', 'flexi340'], ['matte', 'glossy', 'lamination']],
            'spanduk' => ['Spanduk', 'spanduk', 35000, PricingType::PER_SQM, 1, 4, ['flexi280', 'flexi340'], ['matte', 'lamination', 'eyelet']],
            'brosur' => ['Brosur', 'brosur', 2500, PricingType::PER_ITEM, 25, 3, ['artpaper'], ['matte', 'glossy']],
            'flyer' => ['Flyer', 'flyer', 1500, PricingType::PER_ITEM, 50, 2, ['artpaper'], ['matte', 'glossy']],
            'poster' => ['Poster', 'poster', 10000, PricingType::PER_ITEM, 5, 3, ['artpaper'], ['matte', 'lamination']],
            'stiker' => ['Stiker', 'stiker', 3000, PricingType::PER_ITEM, 10, 2, ['flexi280', 'artpaper'], ['glossy', 'lamination']],
            'kartu-nama' => ['Kartu Nama', 'kartu-nama', 7500, PricingType::PER_ITEM, 50, 3, ['artpaper'], ['matte', 'glossy']],
            'undangan' => ['Undangan', 'undangan', 6000, PricingType::PER_ITEM, 25, 4, ['artpaper'], ['matte', 'glossy']],
            'nota' => ['Nota', 'nota', 2000, PricingType::PER_ITEM, 100, 2, ['artpaper'], ['matte']],
            'kop-surat' => ['Kop Surat', 'kop-surat', 4000, PricingType::PER_ITEM, 50, 3, ['artpaper'], ['matte', 'glossy']],
        ];

        $products = [];
        foreach ($productDefinitions as $key => [$name, $slug, $price, $pricingType, $minimumOrder, $productionDays, $materialKeys, $finishingKeys]) {
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categories[$this->categoryForProduct($key)]->id,
                    'name' => $name,
                    'description' => 'Demo product for '.Str::lower($name).'. Sample content and pricing are provided for dashboard testing only.',
                    'specifications' => [
                        'demo' => true,
                        'material_options' => array_map(fn (string $materialKey): string => $materials[$materialKey]->name, $materialKeys),
                        'finishing_options' => array_map(fn (string $finishingKey): string => $finishings[$finishingKey]->name, $finishingKeys),
                    ],
                    'thumbnail' => null,
                    'front_mockup' => 'images/mockups/product-front.svg',
                    'back_mockup' => 'images/mockups/product-back.svg',
                    'base_price' => $price,
                    'status' => 'active',
                    'minimum_order' => $minimumOrder,
                    'production_days' => $productionDays,
                    'popularity_count' => 10 + count($products) * 3,
                ],
            );

            $product->materials()->sync([
                $materials[$materialKeys[0]]->id => ['price_override' => null],
                $materials[$materialKeys[1] ?? $materialKeys[0]]->id => ['price_override' => $price + 5000],
            ]);
            $product->finishings()->sync([
                $finishings[$finishingKeys[0]]->id => ['price_override' => null],
                $finishings[$finishingKeys[1] ?? $finishingKeys[0]]->id => ['price_override' => 5000],
            ]);

            PriceRule::updateOrCreate(
                ['product_id' => $product->id, 'name' => 'Demo base price'],
                [
                    'pricing_type' => $pricingType,
                    'price' => $price,
                    'min_quantity' => 1,
                    'active' => true,
                    'description' => 'Demo price rule. Replace with the approved business tariff before launch.',
                ],
            );

            $products[$key] = $product;
        }

        return compact('categories', 'materials', 'finishings', 'products');
    }

    private function categoryForProduct(string $product): string
    {
        return match ($product) {
            'banner', 'spanduk' => 'banner',
            'brosur', 'flyer' => 'brochure',
            'poster', 'stiker' => 'poster',
            'kartu-nama', 'nota', 'kop-surat' => 'stationery',
            default => 'invitation',
        };
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

        CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $product->id, 'design_reference' => null],
            [
                'quantity' => 2,
                'size' => '3m x 1m',
                'length_cm' => 300,
                'width_cm' => 100,
                'material_id' => null,
                'finishing_id' => null,
                'color' => 'Full Color',
                'production_method' => 'large_format',
                'custom_parameters' => ['demo' => true, 'notes' => 'Demo cart configuration.'],
                'price_at_addition' => 50000,
                'notes' => 'Demo cart item; recalculate the price at checkout.',
            ],
        );
    }

    /**
     * @param  array{admin: User, operator: User, customer: User}  $accounts
     * @param  array{products: array<string, Product>}  $catalog
     */
    private function seedOrders(array $accounts, array $catalog): void
    {
        $customer = Customer::where('user_id', $accounts['customer']->id)->firstOrFail();
        $operator = Operator::where('user_id', $accounts['operator']->id)->firstOrFail();
        $products = $catalog['products'];

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['banner'],
            'KP-DEMO-0001',
            OrderStatus::PENDING_PAYMENT,
            PaymentStatus::UNPAID,
            50000,
            false,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['spanduk'],
            'KP-DEMO-0002',
            OrderStatus::PAYMENT_REVIEW,
            PaymentStatus::WAITING_VERIFICATION,
            70000,
            false,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['kartu-nama'],
            'KP-DEMO-0003',
            OrderStatus::DESIGN_REVIEW,
            PaymentStatus::PAID,
            187500,
            true,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['brosur'],
            'KP-DEMO-0004',
            OrderStatus::IN_PRODUCTION,
            PaymentStatus::PAID,
            125000,
            true,
            ProductionStatus::IN_PRODUCTION,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['undangan'],
            'KP-DEMO-0005',
            OrderStatus::READY,
            PaymentStatus::PAID,
            150000,
            true,
            ProductionStatus::READY,
            true,
        );
    }

    private function seedOrder(
        array $accounts,
        Customer $customer,
        Operator $operator,
        Product $product,
        string $number,
        OrderStatus $orderStatus,
        PaymentStatus $paymentStatus,
        int $lineTotal,
        bool $withDesign,
        ?ProductionStatus $productionStatus = null,
        bool $withQualityCheck = false,
    ): void {
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

        $material = $product->materials()->first();
        $finishing = $product->finishings()->first();

        OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $product->id],
            [
                'product_name' => $product->name,
                'product_reference' => 'DEMO-'.$product->slug,
                'product_slug' => $product->slug,
                'quantity' => $lineTotal >= 100000 ? 25 : 2,
                'size' => 'Demo size',
                'length_cm' => $lineTotal >= 100000 ? 21 : 100,
                'width_cm' => $lineTotal >= 100000 ? 29.7 : 100,
                'material_id' => $material?->id,
                'material_name' => $material?->name,
                'material_reference' => $material?->slug,
                'finishing_id' => $finishing?->id,
                'finishing_name' => $finishing?->name,
                'finishing_reference' => $finishing?->slug,
                'color' => 'Full Color',
                'production_method' => 'digital',
                'custom_parameters' => ['demo' => true],
                'configuration' => ['source' => 'DemoDataSeeder'],
                'design_reference' => null,
                'notes' => 'Demo configuration snapshot.',
                'unit_price' => $lineTotal / max(1, $lineTotal >= 100000 ? 25 : 2),
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
                'method' => 'BANK_TRANSFER',
                'amount' => $lineTotal,
                'bank_name' => 'Bank Demo Indonesia',
                'account_number' => '1234567890',
                'account_name' => 'PT Solusi Print Cepat',
                'proof_path' => null,
                'proof_original_filename' => null,
                'proof_extension' => null,
                'proof_mime_type' => null,
                'proof_size' => null,
                'proof_version' => 1,
                'submitted_at' => $paymentStatus === PaymentStatus::UNPAID ? null : now()->subDays(3),
                'verified_at' => $paymentStatus === PaymentStatus::PAID ? now()->subDays(2) : null,
                'verified_by' => $paymentStatus === PaymentStatus::PAID ? $accounts['admin']->id : null,
                'rejection_reason' => null,
            ],
        );

        $this->history($order, null, OrderStatus::PENDING_PAYMENT, $accounts['customer']->id, 'Demo order created.');
        if ($orderStatus !== OrderStatus::PENDING_PAYMENT) {
            $this->history($order, OrderStatus::PENDING_PAYMENT, OrderStatus::PAYMENT_REVIEW, $accounts['customer']->id, 'Demo payment proof submitted.');
        }
        if (in_array($orderStatus, [OrderStatus::DESIGN_REVIEW, OrderStatus::IN_PRODUCTION, OrderStatus::READY], true)) {
            $this->history($order, OrderStatus::PAYMENT_REVIEW, OrderStatus::PAYMENT_CONFIRMED, $accounts['admin']->id, 'Demo payment verified.');
        }
        if ($orderStatus === OrderStatus::DESIGN_REVIEW) {
            $this->history($order, OrderStatus::PAYMENT_CONFIRMED, OrderStatus::DESIGN_REVIEW, $accounts['customer']->id, 'Demo design submitted for review.');
        }
        if (in_array($orderStatus, [OrderStatus::IN_PRODUCTION, OrderStatus::READY], true)) {
            $this->history($order, OrderStatus::PAYMENT_CONFIRMED, OrderStatus::DESIGN_APPROVED, $accounts['admin']->id, 'Demo design approved.');
            $this->history($order, OrderStatus::DESIGN_APPROVED, OrderStatus::WAITING_PRODUCTION, $accounts['admin']->id, 'Demo production task assigned.');
        }
        if ($orderStatus === OrderStatus::IN_PRODUCTION) {
            $this->history($order, OrderStatus::WAITING_PRODUCTION, OrderStatus::IN_PRODUCTION, $accounts['operator']->id, 'Demo production started.');
        }
        if ($orderStatus === OrderStatus::READY) {
            $this->history($order, OrderStatus::WAITING_PRODUCTION, OrderStatus::IN_PRODUCTION, $accounts['operator']->id, 'Demo production started.');
            $this->history($order, OrderStatus::IN_PRODUCTION, OrderStatus::FINISHING, $accounts['operator']->id, 'Demo finishing completed.');
            $this->history($order, OrderStatus::FINISHING, OrderStatus::QUALITY_CHECK, $accounts['operator']->id, 'Demo job sent to quality check.');
            $this->history($order, OrderStatus::QUALITY_CHECK, OrderStatus::READY, $accounts['operator']->id, 'Demo quality check passed.');
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
                    'status' => $orderStatus === OrderStatus::DESIGN_REVIEW ? DesignStatus::PENDING : DesignStatus::APPROVED,
                    'notes' => 'Demo design metadata; no binary file is required for local seeding.',
                    'review_note' => $orderStatus === OrderStatus::DESIGN_REVIEW ? 'Demo review is waiting for approval.' : null,
                    'reviewed_by' => $orderStatus === OrderStatus::DESIGN_REVIEW ? null : $accounts['admin']->id,
                    'reviewed_at' => $orderStatus === OrderStatus::DESIGN_REVIEW ? null : now()->subDays(1),
                    'uploaded_by' => $accounts['customer']->id,
                    'uploaded_at' => now()->subDays(2),
                ],
            );
        }

        if ($productionStatus !== null) {
            $production = ProductionOrder::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'operator_id' => $operator->id,
                    'status' => $productionStatus,
                    'progress' => $productionStatus === ProductionStatus::READY ? 100 : 45,
                    'notes' => 'Demo production record. This is sample data for dashboard testing.',
                    'deadline' => now()->addDays(5),
                    'assigned_at' => now()->subDays(1),
                    'started_at' => now()->subDays(1),
                    'finished_at' => $productionStatus === ProductionStatus::READY ? now()->subHours(4) : null,
                    'completed_at' => null,
                ],
            );

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
                ['production_order_id' => $production->id, 'new_status' => ProductionStatus::IN_PRODUCTION->value],
                [
                    'changed_by' => $accounts['operator']->id,
                    'old_status' => ProductionStatus::WAITING_PRODUCTION->value,
                    'progress' => 10,
                    'note' => 'Demo production started.',
                ],
            );

            if ($productionStatus === ProductionStatus::READY) {
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::FINISHING->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::IN_PRODUCTION->value,
                        'progress' => 80,
                        'note' => 'Demo finishing completed.',
                    ],
                );
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::QUALITY_CHECK->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::FINISHING->value,
                        'progress' => 95,
                        'note' => 'Demo quality check submitted.',
                    ],
                );
                ProductionStatusHistory::firstOrCreate(
                    ['production_order_id' => $production->id, 'new_status' => ProductionStatus::READY->value],
                    [
                        'changed_by' => $accounts['operator']->id,
                        'old_status' => ProductionStatus::QUALITY_CHECK->value,
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

    private function history(Order $order, ?OrderStatus $old, OrderStatus $new, int $changedBy, string $note): void
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
