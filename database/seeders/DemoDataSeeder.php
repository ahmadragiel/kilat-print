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
use App\Models\DesignApproval;
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
        $this->seedCart($accounts['customer'], $catalog['products']['mug-custom']);
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
     * Master data katalog Kilat Print.
     *
     * Produk, kategori, material, dan finishing di bawah ini mengikuti produk yang
     * disebut pada laporan Kerja Praktek: Mug Custom, Bando Tuning Custom,
     * Paper Bag Custom, Kertas Kado, Apotek Mini, Topper Cake, dan Topeng Muka.
     * Harga, minimum order, dan hari produksi berstatus data demo untuk kalkulator
     * aplikasi, bukan klaim tarif resmi perusahaan.
     *
     * @return array{categories: array<string, Category>, materials: array<string, Material>, finishings: array<string, Finishing>, products: array<string, Product>}
     */
    private function seedCatalog(): array
    {
        $categories = $this->seedCategories();
        $materials = $this->seedMaterials();
        $finishings = $this->seedFinishings();
        $products = $this->seedProducts($categories, $materials, $finishings);

        return compact('categories', 'materials', 'finishings', 'products');
    }

    /** @return array<string, Category> */
    private function seedCategories(): array
    {
        $definitions = [
            'custom' => [
                'name' => 'Produk Custom',
                'description' => 'Produk custom Kilat Print: Mug Custom, Bando Tuning Custom, Topper Cake, dan Topeng Muka.',
            ],
            'printing-custom' => [
                'name' => 'Printing Custom',
                'description' => 'Printing custom Kilat Print untuk kebutuhan cetak dan kemasan: Paper Bag Custom, Kertas Kado, dan Apotek Mini.',
            ],
        ];

        $categories = [];
        foreach ($definitions as $key => $definition) {
            $categories[$key] = Category::updateOrCreate(
                ['slug' => Str::slug($definition['name'])],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'image' => null,
                    'status' => 'active',
                ],
            );
        }

        return $categories;
    }

    /**
     * Pilihan bahan bersifat generik karena laporan KP hanya menyatakan bahwa bahan
     * menyesuaikan jenis produk. Harga bahan di sini adalah data demo kalkulator.
     *
     * @return array<string, Material>
     */
    private function seedMaterials(): array
    {
        $definitions = [
            'mug' => ['Bahan Mug', 'Bahan dasar untuk Mug Custom.'],
            'bando' => ['Bahan Bando', 'Bahan dasar untuk Bando Tuning Custom.'],
            'kraft' => ['Kertas Kraft', 'Kertas kemasan untuk Paper Bag Custom.'],
            'kertas-cetak' => ['Kertas Cetak', 'Kertas cetak untuk produk kertas Kilat Print.'],
        ];

        $materials = [];
        foreach ($definitions as $key => [$name, $usage]) {
            $materials[$key] = Material::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $usage.' Data demo untuk kalkulator harga, bukan daftar material resmi Kilat Print.',
                    'pricing_type' => PricingType::PER_ITEM,
                    'price' => 0,
                    'status' => 'active',
                ],
            );
        }

        return $materials;
    }

    /** @return array<string, Finishing> */
    private function seedFinishings(): array
    {
        $definitions = [
            'matte' => ['Matte', 0],
            'glossy' => ['Glossy', 5000],
            'lamination' => ['Laminasi', 8000],
        ];

        $finishings = [];
        foreach ($definitions as $key => [$name, $price]) {
            $finishings[$key] = Finishing::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => 'Pilihan finishing Kilat Print. Harga pada seeder ini data demo, bukan tarif resmi perusahaan.',
                    'pricing_type' => PricingType::PER_ITEM,
                    'price' => $price,
                    'status' => 'active',
                ],
            );
        }

        return $finishings;
    }

    /**
     * Definisi produk Kilat Print. Kunci material/finishing berisi tambahan biaya
     * demo per item; null berarti memakai harga master pada tabel material/finishing.
     *
     * @param  array<string, Category>  $categories
     * @param  array<string, Material>  $materials
     * @param  array<string, Finishing>  $finishings
     * @return array<string, Product>
     */
    private function seedProducts(array $categories, array $materials, array $finishings): array
    {
        $definitions = [
            'mug-custom' => [
                'name' => 'Mug Custom',
                'category' => 'custom',
                'description' => 'Produk mug custom yang dapat disesuaikan dengan desain dan kebutuhan pelanggan.',
                'base_price' => 25000,
                'minimum_order' => 1,
                'production_days' => 3,
                'materials' => ['mug' => null],
                'finishings' => ['matte' => null, 'glossy' => 3000],
                'is_featured' => true,
                'popularity_count' => 42,
            ],
            'bando-tuning-custom' => [
                'name' => 'Bando Tuning Custom',
                'category' => 'custom',
                'description' => 'Produk bando tuning custom yang dapat disesuaikan dengan desain dan kebutuhan pelanggan.',
                'base_price' => 35000,
                'minimum_order' => 1,
                'production_days' => 3,
                'materials' => ['bando' => null],
                'finishings' => ['matte' => null, 'glossy' => 3000],
                'is_featured' => true,
                'popularity_count' => 35,
            ],
            'paper-bag-custom' => [
                'name' => 'Paper Bag Custom',
                'category' => 'printing-custom',
                'description' => 'Produk paper bag custom untuk kebutuhan cetak dan kemasan sesuai kebutuhan pelanggan.',
                'base_price' => 4500,
                'minimum_order' => 1,
                'production_days' => 4,
                'materials' => ['kraft' => null, 'kertas-cetak' => 2000],
                'finishings' => ['matte' => null, 'lamination' => 2500],
                'is_featured' => true,
                'popularity_count' => 28,
            ],
            'kertas-kado' => [
                'name' => 'Kertas Kado',
                'category' => 'printing-custom',
                'description' => 'Produk kertas kado untuk kebutuhan cetak sesuai desain pelanggan.',
                'base_price' => 3000,
                'minimum_order' => 1,
                'production_days' => 2,
                'materials' => ['kertas-cetak' => null],
                'finishings' => ['glossy' => 1500, 'lamination' => 2000],
                'is_featured' => false,
                'popularity_count' => 21,
            ],
            'apotek-mini' => [
                'name' => 'Apotek Mini',
                'category' => 'printing-custom',
                'description' => 'Produk apotek mini untuk kebutuhan cetak dan kemasan sesuai desain pelanggan.',
                'base_price' => 8500,
                'minimum_order' => 1,
                'production_days' => 3,
                'materials' => ['kertas-cetak' => null],
                'finishings' => ['matte' => null, 'glossy' => 1500],
                'is_featured' => false,
                'popularity_count' => 11,
            ],
            'topper-cake' => [
                'name' => 'Topper Cake',
                'category' => 'custom',
                'description' => 'Produk topper cake yang dapat disesuaikan dengan desain dan kebutuhan pelanggan.',
                'base_price' => 12000,
                'minimum_order' => 1,
                'production_days' => 2,
                'materials' => ['kertas-cetak' => null],
                'finishings' => ['matte' => null, 'glossy' => 1500],
                'is_featured' => false,
                'popularity_count' => 17,
            ],
            'topeng-muka' => [
                'name' => 'Topeng Muka',
                'category' => 'custom',
                'description' => 'Produk topeng muka yang dapat disesuaikan dengan desain dan kebutuhan pelanggan.',
                'base_price' => 15000,
                'minimum_order' => 1,
                'production_days' => 3,
                'materials' => ['kertas-cetak' => null],
                'finishings' => ['matte' => null, 'glossy' => 1500],
                'is_featured' => false,
                'popularity_count' => 14,
            ],
        ];

        $products = [];
        foreach ($definitions as $key => $definition) {
            $slug = Str::slug($definition['name']);

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $categories[$definition['category']]->id,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'specifications' => [
                        'demo' => true,
                        'size' => 'Sesuai kebutuhan pelanggan',
                        'material' => $this->optionNames($materials, $definition['materials']),
                        'finishing' => $this->optionNames($finishings, $definition['finishings']),
                        'catatan' => 'Data demo untuk pengembangan aplikasi. Ganti dengan spesifikasi resmi Kilat Print sebelum dipakai nyata.',
                    ],
                    'thumbnail' => 'images/products/'.$slug.'.svg',
                    'front_mockup' => 'images/products/'.$slug.'.svg',
                    'back_mockup' => 'images/mockups/product-back.svg',
                    'base_price' => $definition['base_price'],
                    'status' => 'active',
                    'is_featured' => $definition['is_featured'],
                    'minimum_order' => $definition['minimum_order'],
                    'production_days' => $definition['production_days'],
                    'popularity_count' => $definition['popularity_count'],
                ],
            );

            $product->materials()->sync($this->optionSync($materials, $definition['materials']));
            $product->finishings()->sync($this->optionSync($finishings, $definition['finishings']));

            // Hapus aturan harga lama (misalnya aturan per meter persegi dari katalog demo
            // sebelumnya) supaya kalkulator tidak menjumlahkan dua basis harga.
            $product->priceRules()->where('name', '!=', 'Harga demo per item')->delete();

            PriceRule::updateOrCreate(
                ['product_id' => $product->id, 'name' => 'Harga demo per item'],
                [
                    'pricing_type' => PricingType::PER_ITEM,
                    'price' => $definition['base_price'],
                    'min_quantity' => 1,
                    'active' => true,
                    'description' => 'Harga demo per item untuk kalkulator aplikasi. Ganti dengan tarif resmi Kilat Print sebelum dipakai nyata.',
                ],
            );

            $products[$key] = $product;
        }

        return $products;
    }

    /**
     * @param  array<string, Material|Finishing>  $options
     * @param  array<string, int|null>  $selection  option key => surcharge per item
     * @return array<int, array{price_override: int|null}>
     */
    private function optionSync(array $options, array $selection): array
    {
        $payload = [];
        foreach ($selection as $key => $surcharge) {
            $payload[$options[$key]->id] = ['price_override' => $surcharge];
        }

        return $payload;
    }

    /**
     * @param  array<string, Material|Finishing>  $options
     * @param  array<string, int|null>  $selection
     */
    private function optionNames(array $options, array $selection): string
    {
        return collect(array_keys($selection))
            ->map(fn (string $key): string => $options[$key]->name)
            ->implode(', ');
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
     * @param  array{products: array<string, Product>}  $catalog
     */
    private function seedOrders(array $accounts, array $catalog): void
    {
        $customer = Customer::where('user_id', $accounts['customer']->id)->firstOrFail();
        $operator = Operator::where('user_id', $accounts['operator']->id)->firstOrFail();
        $products = $catalog['products'];

        // Jumlah pesanan demo mengikuti katalog laporan KP; seluruh produk dihitung
        // per item sehingga tidak ada dimensi luas (meter persegi) pada order item.
        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['mug-custom'],
            'KP-DEMO-0001',
            OrderStatus::PENDING_PAYMENT,
            PaymentStatus::UNPAID,
            2,
            false,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['bando-tuning-custom'],
            'KP-DEMO-0002',
            OrderStatus::PAYMENT_REVIEW,
            PaymentStatus::WAITING_VERIFICATION,
            2,
            false,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['kertas-kado'],
            'KP-DEMO-0003',
            OrderStatus::DESIGN_REVIEW,
            PaymentStatus::PAID,
            50,
            true,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['paper-bag-custom'],
            'KP-DEMO-0004',
            OrderStatus::IN_PRODUCTION,
            PaymentStatus::PAID,
            100,
            true,
            ProductionStatus::Printing,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['topper-cake'],
            'KP-DEMO-0005',
            OrderStatus::COMPLETED,
            PaymentStatus::PAID,
            12,
            true,
            ProductionStatus::Completed,
            true,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['topeng-muka'],
            'KP-DEMO-0006',
            OrderStatus::DESIGN_REVIEW,
            PaymentStatus::PAID,
            6,
            true,
        );

        $this->seedOrder(
            $accounts,
            $customer,
            $operator,
            $products['apotek-mini'],
            'KP-DEMO-0007',
            OrderStatus::IN_PRODUCTION,
            PaymentStatus::PAID,
            15,
            true,
            ProductionStatus::Printing,
        );

        $awaitingApprovalOrder = Order::where('number', 'KP-DEMO-0006')->first();
        if ($awaitingApprovalOrder && ($pendingDesign = $awaitingApprovalOrder->designFiles()->first())) {
            $pendingDesign->update([
                'status' => DesignStatus::AwaitingCustomerApproval,
                'review_note' => null,
                'reviewed_by' => $accounts['admin']->id,
                'reviewed_at' => now()->subDay(),
            ]);
            DesignApproval::updateOrCreate(
                ['design_file_id' => $pendingDesign->id],
                [
                    'order_id' => $awaitingApprovalOrder->id,
                    'customer_id' => $accounts['customer']->id,
                    'status' => 'PENDING',
                    'requested_by' => $accounts['admin']->id,
                    'requested_at' => now()->subDay(),
                ],
            );
        }

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
    ): void {
        $unitPrice = (int) $product->base_price;
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

        $material = $product->materials()->first();
        $finishing = $product->finishings()->first();

        OrderItem::updateOrCreate(
            ['order_id' => $order->id, 'product_id' => $product->id],
            [
                'product_name' => $product->name,
                'product_reference' => 'DEMO-'.$product->slug,
                'product_slug' => $product->slug,
                'quantity' => $quantity,
                'size' => null,
                'length_cm' => null,
                'width_cm' => null,
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
        if (in_array($orderStatus, [OrderStatus::DESIGN_REVIEW, OrderStatus::IN_PRODUCTION, OrderStatus::COMPLETED], true)) {
            $this->history($order, OrderStatus::PAYMENT_REVIEW, OrderStatus::PAYMENT_CONFIRMED, $accounts['admin']->id, 'Demo payment verified.');
        }
        if ($orderStatus === OrderStatus::DESIGN_REVIEW) {
            $this->history($order, OrderStatus::PAYMENT_CONFIRMED, OrderStatus::DESIGN_REVIEW, $accounts['customer']->id, 'Demo design submitted for review.');
        }
        if (in_array($orderStatus, [OrderStatus::IN_PRODUCTION, OrderStatus::COMPLETED], true)) {
            $this->history($order, OrderStatus::PAYMENT_CONFIRMED, OrderStatus::DESIGN_APPROVED, $accounts['customer']->id, 'Demo design approved by customer.');
            $this->history($order, OrderStatus::DESIGN_APPROVED, OrderStatus::IN_PRODUCTION, $accounts['admin']->id, 'Demo production task assigned.');
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
                    'progress' => $productionStatus === ProductionStatus::Completed ? 100 : 45,
                    'notes' => 'Demo production record. This is sample data for dashboard testing.',
                    'deadline' => now()->addDays(5),
                    'assigned_at' => now()->subDays(1),
                    'started_at' => now()->subDays(1),
                    'finished_at' => $productionStatus === ProductionStatus::Completed ? now()->subHours(4) : null,
                    'completed_at' => $productionStatus === ProductionStatus::Completed ? now()->subHours(4) : null,
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
