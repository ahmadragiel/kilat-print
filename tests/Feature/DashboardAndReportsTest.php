<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductionStatus;
use Tests\TestCase;

class DashboardAndReportsTest extends TestCase
{
    public function test_customer_dashboard_uses_the_customers_real_orders(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(price: 15000);
        $order = $this->makeOrder($customer, [
            'status' => OrderStatus::IN_PRODUCTION,
            'grand_total' => 30000,
            'subtotal' => 30000,
        ]);
        $this->makeOrderItem($order, $product, ['quantity' => 2, 'line_total' => 30000]);
        $this->makePayment($order, ['status' => PaymentStatus::PAID]);

        $response = $this->actingAs($customer->user)->get(route('customer.dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('totalOrders'));
        $this->assertSame(1, $response->viewData('activeOrders'));
        $this->assertTrue($response->viewData('recentOrders')->contains($order));
    }

    public function test_operator_dashboard_uses_only_assigned_production_jobs(): void
    {
        $operator = $this->makeOperator();
        $customer = $this->makeCustomer();
        $assignedOrder = $this->makeOrder($customer, ['status' => OrderStatus::WAITING_PRODUCTION]);
        $unassignedOrder = $this->makeOrder($customer, ['status' => OrderStatus::WAITING_PRODUCTION]);
        $assigned = $this->makeProduction($assignedOrder, [
            'operator_id' => $operator->id,
            'status' => ProductionStatus::IN_PRODUCTION,
        ]);
        $unassigned = $this->makeProduction($unassignedOrder);

        $response = $this->actingAs($operator->user)->get(route('operator.dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('totalJobs'));
        $this->assertSame(1, $response->viewData('inProduction'));
        $this->assertTrue($response->viewData('recentJobs')->contains($assigned));
        $this->assertFalse($response->viewData('recentJobs')->contains($unassigned));
    }

    public function test_admin_dashboard_uses_created_orders_customers_and_order_items(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(price: 10000, attributes: [
            'name' => 'Dashboard Product',
        ]);
        $order = $this->makeOrder($customer, [
            'status' => OrderStatus::PAYMENT_CONFIRMED,
            'subtotal' => 20000,
            'grand_total' => 20000,
            'paid_at' => now(),
        ]);
        $this->makeOrderItem($order, $product, [
            'quantity' => 2,
            'unit_price' => 10000,
            'line_total' => 20000,
        ]);
        $this->makePayment($order, [
            'status' => PaymentStatus::PAID,
            'amount' => 20000,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('totalOrders'));
        $this->assertSame(1, $response->viewData('customers'));
        $this->assertSame(1, $response->viewData('activeOrders'));
        $this->assertSame(20000, $response->viewData('revenue'));
        $this->assertSame('Dashboard Product', $response->viewData('bestSellingProduct'));
    }

    public function test_admin_report_uses_real_records_and_date_filters(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(price: 10000, attributes: [
            'name' => 'Reported Product',
        ]);
        $order = $this->makeOrder($customer, [
            'status' => OrderStatus::COMPLETED,
            'subtotal' => 30000,
            'grand_total' => 30000,
            'created_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
        ]);
        $this->makeOrderItem($order, $product, [
            'quantity' => 3,
            'unit_price' => 10000,
            'line_total' => 30000,
        ]);
        $this->makePayment($order, [
            'status' => PaymentStatus::PAID,
            'amount' => 30000,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.reports.index', [
            'from' => now()->subDays(5)->toDateString(),
            'to' => now()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('kpi')['orders']);
        $this->assertSame(30000, $response->viewData('kpi')['revenue']);
        $this->assertSame(1, $response->viewData('kpi')['completed']);
        $this->assertSame('Reported Product', $response->viewData('topProducts')->first()->product_name);
        $this->assertSame(3, (int) $response->viewData('topProducts')->first()->quantity_sold);
    }

    public function test_role_dashboard_routes_reject_users_from_other_role_sections(): void
    {
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();

        $this->actingAs($customer->user)->get(route('operator.dashboard'))->assertForbidden();
        $this->actingAs($operator->user)->get(route('customer.dashboard'))->assertForbidden();
    }
}
