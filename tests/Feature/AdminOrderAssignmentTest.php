<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminOrderAssignmentTest extends TestCase
{
    public function test_admin_order_detail_lists_only_active_operators_for_assignment(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->makeOrderItem($order, $this->makeProduct());
        $this->makePaidOrder($order);
        $production = $this->makeProduction($order);
        $this->makeOperator(['name' => 'Operator Siap Tugas']);

        $this->makeOperator(['name' => 'Operator Nonaktif'], ['is_active' => false]);
        $this->makeOperator(['name' => 'Akun Operator Nonaktif'], ['is_active' => false]);

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertSee('Tugaskan operator');
        $response->assertSee(route('admin.production.assign', $production), false);
        $response->assertSee('Operator Siap Tugas');
        $response->assertDontSee('Operator Nonaktif');
        $response->assertDontSee('Akun Operator Nonaktif');
    }

    public function test_assignment_form_is_hidden_until_a_production_record_exists(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->makeOrderItem($order, $this->makeProduct());

        $response = $this->actingAs($admin)->get(route('admin.orders.show', $order));

        $response->assertOk();
        $response->assertDontSee('Tugaskan operator');
        $response->assertDontSee('name="operator_id"', false);
    }
}
