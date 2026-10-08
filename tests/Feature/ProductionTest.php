<?php

namespace Tests\Feature;

use App\Enums\DesignStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionStatus;
use App\Services\ProductionService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_admin_assigns_job_and_operator_runs_production_through_quality_check_pass(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::DESIGN_APPROVED]);
        $this->makeDesign($order, ['status' => DesignStatus::APPROVED]);
        $production = $this->makeProduction($order);

        $this->actingAs($admin)
            ->post(route('admin.production.assign', $production), [
                'operator_id' => $operator->id,
                'deadline' => now()->addDays(2)->toDateString(),
            ])
            ->assertSessionHas('success');

        $production->refresh();
        $this->assertSame($operator->id, $production->operator_id);
        $this->assertNotNull($production->assigned_at);
        $this->assertDatabaseHas('production_assignments', [
            'production_order_id' => $production->id,
            'operator_id' => $operator->id,
            'assigned_by' => $admin->id,
            'is_current' => true,
        ]);
        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->fresh()->status);

        $this->actingAs($operator->user)
            ->post(route('operator.jobs.status', $production), ['status' => 'PRINTING'])
            ->assertSessionHas('success');
        $this->actingAs($operator->user)
            ->post(route('operator.jobs.status', $production), ['status' => 'FINISHING', 'progress' => 60])
            ->assertSessionHas('success');
        $this->actingAs($operator->user)
            ->post(route('operator.jobs.status', $production), ['status' => 'PACKING', 'progress' => 80])
            ->assertSessionHas('success');
        $this->actingAs($operator->user)
            ->post(route('operator.jobs.status', $production), ['status' => 'QUALITY_CONTROL', 'progress' => 90])
            ->assertSessionHas('success');
        $this->actingAs($operator->user)
            ->post(route('operator.jobs.quality-check', $production), [
                'result' => 'PASS',
                'notes' => 'Color and dimensions match proof.',
            ])
            ->assertSessionHas('success');

        $production->refresh();
        $this->assertSame(ProductionStatus::COMPLETED, $production->status);
        $this->assertSame(100, $production->progress);
        $this->assertNotNull($production->started_at);
        $this->assertNotNull($production->finished_at);
        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->status);
        $this->assertDatabaseHas('quality_checks', [
            'production_order_id' => $production->id,
            'result' => 'PASS',
            'notes' => 'Color and dimensions match proof.',
        ]);
        $this->assertDatabaseHas('production_status_histories', [
            'production_order_id' => $production->id,
            'new_status' => ProductionStatus::Completed->value,
        ]);
    }

    public function test_failed_quality_check_returns_job_to_production_for_rework(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::DESIGN_APPROVED]);
        $this->makeDesign($order, ['status' => DesignStatus::APPROVED]);
        $production = $this->makeProduction($order);
        app(ProductionService::class)->assign($production, $operator, $admin);

        $this->actingAs($operator->user)->post(route('operator.jobs.status', $production), ['status' => 'PRINTING']);
        $this->actingAs($operator->user)->post(route('operator.jobs.status', $production), ['status' => 'FINISHING']);
        $this->actingAs($operator->user)->post(route('operator.jobs.status', $production), ['status' => 'PACKING']);
        $this->actingAs($operator->user)->post(route('operator.jobs.status', $production), ['status' => 'QUALITY_CONTROL']);
        $this->actingAs($operator->user)
            ->post(route('operator.jobs.quality-check', $production), [
                'result' => 'FAIL',
                'notes' => 'Please replace the damaged panel.',
            ])
            ->assertSessionHas('success');

        $production->refresh();
        $this->assertSame(ProductionStatus::PRINTING, $production->status);
        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->fresh()->status);
        $this->assertDatabaseHas('quality_checks', [
            'production_order_id' => $production->id,
            'result' => 'FAIL',
        ]);
        $this->assertDatabaseHas('production_status_histories', [
            'production_order_id' => $production->id,
            'old_status' => ProductionStatus::QUALITY_CONTROL->value,
            'new_status' => ProductionStatus::PRINTING->value,
        ]);
    }

    public function test_operator_cannot_start_or_finish_a_job_after_assignment_is_removed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $operator = $this->makeOperator();
        $order = $this->makeOrder($customer, ['status' => OrderStatus::DESIGN_APPROVED]);
        $this->makeDesign($order, ['status' => DesignStatus::APPROVED]);
        $production = $this->makeProduction($order);
        app(ProductionService::class)->assign($production, $operator, $admin);
        $production->update(['operator_id' => null]);

        $this->actingAs($operator->user)
            ->post(route('operator.jobs.status', $production), ['status' => 'PRINTING'])
            ->assertForbidden();
        $this->assertSame(OrderStatus::IN_PRODUCTION, $order->fresh()->status);
    }
}
