<?php

namespace Tests\Concerns;

use App\Enums\OrderStatus;
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
use App\Models\Operator;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait CreatesTestData
{
    protected function makeCustomer(array $userAttributes = [], array $customerAttributes = []): Customer
    {
        $user = User::factory()->customer()->create(array_merge([
            'name' => 'Customer Test',
            'email' => 'customer-'.Str::lower(Str::random(8)).'@example.test',
            'is_active' => true,
        ], $userAttributes));

        return Customer::factory()->create(array_merge([
            'customer_code' => 'CUST-'.$user->id,
            'phone' => '081200000001',
            'is_active' => true,
        ], $customerAttributes, ['user_id' => $user->id]))->load('user');
    }

    protected function makeAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create(array_merge([
            'name' => 'Admin Test',
            'email' => 'admin-'.Str::lower(Str::random(8)).'@example.test',
            'is_active' => true,
        ], $attributes));
    }

    protected function makeOperator(array $userAttributes = [], array $operatorAttributes = []): Operator
    {
        $user = User::factory()->operator()->create(array_merge([
            'name' => 'Operator Test',
            'email' => 'operator-'.Str::lower(Str::random(8)).'@example.test',
            'is_active' => true,
        ], $userAttributes));

        return Operator::create(array_merge([
            'employee_code' => 'OP-'.$user->id,
            'phone' => '081200000002',
            'specialization' => 'Digital printing',
            'is_active' => true,
        ], $operatorAttributes, ['user_id' => $user->id]))->load('user');
    }

    /**
     * Create a product with one active tariff. The helper deliberately does not
     * use DemoDataSeeder so every test owns its records and pricing.
     */
    protected function makeProduct(
        PricingType|string $pricingType = PricingType::PER_ITEM,
        int|float $price = 10000,
        array $attributes = [],
    ): Product {
        $category = $attributes['category'] ?? Category::factory()->create([
            'name' => 'Test Category '.Str::upper(Str::random(4)),
            'slug' => 'test-category-'.Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        unset($attributes['category']);

        $name = $attributes['name'] ?? 'Test Product '.Str::upper(Str::random(4));
        $slug = $attributes['slug'] ?? 'test-product-'.Str::lower(Str::random(8));

        $product = Product::factory()->create(array_merge([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => $slug,
            'description' => 'A product created for a focused feature test.',
            'specifications' => ['test' => true],
            'base_price' => $price,
            'status' => 'active',
            'minimum_order' => 1,
            'production_days' => 2,
            'popularity_count' => 0,
        ], $attributes));

        PriceRule::create([
            'product_id' => $product->id,
            'name' => 'Test tariff',
            'pricing_type' => $pricingType,
            'price' => $price,
            'min_quantity' => 1,
            'active' => true,
        ]);

        return $product->fresh(['category', 'priceRules']);
    }

    protected function attachMaterial(
        Product $product,
        PricingType|string $pricingType = PricingType::PER_ITEM,
        int|float $price = 1000,
        int|float|null $override = null,
        array $attributes = [],
    ): Material {
        $material = Material::create(array_merge([
            'name' => 'Test Material '.Str::upper(Str::random(4)),
            'slug' => 'test-material-'.Str::lower(Str::random(8)),
            'description' => 'Material for a focused test.',
            'pricing_type' => $pricingType,
            'price' => $price,
            'status' => 'active',
        ], $attributes));

        $product->materials()->attach($material, ['price_override' => $override]);

        return $material->fresh();
    }

    protected function attachFinishing(
        Product $product,
        PricingType|string $pricingType = PricingType::PER_ITEM,
        int|float $price = 1000,
        int|float|null $override = null,
        array $attributes = [],
    ): Finishing {
        $finishing = Finishing::create(array_merge([
            'name' => 'Test Finishing '.Str::upper(Str::random(4)),
            'slug' => 'test-finishing-'.Str::lower(Str::random(8)),
            'description' => 'Finishing for a focused test.',
            'pricing_type' => $pricingType,
            'price' => $price,
            'status' => 'active',
        ], $attributes));

        $product->finishings()->attach($finishing, ['price_override' => $override]);

        return $finishing->fresh();
    }

    protected function makeAddress(Customer $customer, array $attributes = []): Address
    {
        return Address::create(array_merge([
            'customer_id' => $customer->id,
            'label' => 'Rumah',
            'recipient' => $customer->user->name,
            'phone' => '081200000001',
            'address' => 'Jalan Test No. 1',
            'district' => 'Nginden',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'postal_code' => '60111',
            'country' => 'Indonesia',
            'is_primary' => true,
        ], $attributes));
    }

    protected function makeCart(Customer $customer): Cart
    {
        return Cart::firstOrCreate(['customer_id' => $customer->id]);
    }

    protected function makeCartItem(
        Cart $cart,
        Product $product,
        int $quantity = 1,
        array $attributes = [],
    ): CartItem {
        return $cart->items()->create(array_merge([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'production_method' => 'digital',
            'price_at_addition' => 10000,
        ], $attributes));
    }

    protected function makeOrder(Customer $customer, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'number' => 'KP-TEST-'.Str::upper(Str::random(10)),
            'customer_id' => $customer->id,
            'customer_name' => $customer->user->name,
            'customer_phone' => $customer->phone,
            'customer_email' => $customer->user->email,
            'status' => OrderStatus::PENDING_PAYMENT,
            'subtotal' => 10000,
            'shipping_fee' => 0,
            'grand_total' => 10000,
            'shipping_method' => 'pickup',
            'deadline' => now()->addDays(3),
        ], $attributes));
    }

    protected function makeOrderItem(Order $order, Product $product, array $attributes = []): OrderItem
    {
        return $order->items()->create(array_merge([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'quantity' => 1,
            'production_method' => 'digital',
            'unit_price' => 10000,
            'line_total' => 10000,
        ], $attributes));
    }

    protected function makeOrderAddress(Order $order, array $attributes = []): OrderAddress
    {
        return $order->address()->create(array_merge([
            'recipient' => 'Test Recipient',
            'phone' => '081200000001',
            'address' => 'Jalan Test No. 1',
            'district' => 'Nginden',
            'city' => 'Surabaya',
            'province' => 'Jawa Timur',
            'postal_code' => '60111',
            'country' => 'Indonesia',
            'shipping_method' => 'pickup',
        ], $attributes));
    }

    protected function makePayment(Order $order, array $attributes = []): Payment
    {
        return $order->payment()->create(array_merge([
            'status' => 'UNPAID',
            'method' => 'BANK_TRANSFER',
            'amount' => $order->grand_total,
            'bank_name' => 'Bank Test',
            'account_number' => '1234567890',
            'account_name' => 'Kilat Print',
            'proof_version' => 1,
        ], $attributes));
    }

    protected function makeProduction(Order $order, array $attributes = []): ProductionOrder
    {
        return $order->production()->create(array_merge([
            'status' => ProductionStatus::WAITING_PRODUCTION,
            'progress' => 0,
        ], $attributes));
    }

    protected function makeDesign(Order $order, array $attributes = []): DesignFile
    {
        return $order->designFiles()->create(array_merge([
            'original_filename' => 'design.pdf',
            'stored_filename' => 'design.pdf',
            'path' => 'designs/'.$order->id.'/design.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'size' => 4,
            'version' => 1,
            'status' => 'Pending',
            'uploaded_by' => $order->customer->user_id,
            'uploaded_at' => now(),
        ], $attributes));
    }

    protected function fakePrivateStorage(): void
    {
        Storage::fake('local');
    }

    protected function userRole(User $user): UserRole
    {
        return $user->role instanceof UserRole ? $user->role : UserRole::from($user->role);
    }
}
