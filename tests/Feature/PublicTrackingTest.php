<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicTrackingTest extends TestCase
{
    public function test_tracking_route_is_rate_limited(): void
    {
        $route = Route::getRoutes()->getByName('tracking.index');

        $this->assertNotNull($route);
        $this->assertContains('throttle:30,1', $route->gatherMiddleware());
    }

    public function test_tracking_page_still_finds_an_order_by_its_number(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['number' => 'KP-20261025-0099']);
        $this->makeOrderItem($order, $this->makeProduct(attributes: ['name' => 'Produk Lacak']));

        $this->get(route('tracking.index', ['number' => $order->number]))
            ->assertOk()
            ->assertSee($order->number)
            ->assertSee('Produk Lacak');
    }
}
