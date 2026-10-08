<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAndAuthorizationTest extends TestCase
{
    public function test_registration_creates_a_customer_profile_and_redirects_to_customer_dashboard(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Registered Customer',
            'email' => 'registered-customer@example.test',
            'phone' => '081298765432',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'registered-customer@example.test')->firstOrFail();
        $this->assertSame(UserRole::Customer, $user->role);
        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'phone' => '081298765432',
        ]);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_login_logout_and_role_based_destinations_work_for_each_role(): void
    {
        $customer = $this->makeCustomer(['email' => 'login-customer@example.test']);
        $admin = $this->makeAdmin(['email' => 'login-admin@example.test']);
        $operator = $this->makeOperator(['email' => 'login-operator@example.test']);

        $destinations = [
            [$customer->user, route('customer.dashboard')],
            [$admin, route('admin.dashboard')],
            [$operator->user, route('operator.dashboard')],
        ];

        foreach ($destinations as [$user, $destination]) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect($destination);

            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))
                ->assertRedirect(route('home'))
                ->assertSessionHas('logout_notice', 'Anda telah logout.');
            $this->assertGuest();
        }
    }

    public function test_role_boundaries_and_guest_redirects_are_enforced(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        $customer = $this->makeCustomer();
        $this->actingAs($customer->user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
        $this->actingAs($customer->user)
            ->get(route('operator.dashboard'))
            ->assertForbidden();

        $operator = $this->makeOperator();
        $this->actingAs($operator->user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_customer_cannot_view_or_mutate_another_customers_order_and_address(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $product = $this->makeProduct();
        $order = $this->makeOrder($owner);
        $this->makeOrderItem($order, $product);
        $address = $this->makeAddress($owner);

        $this->actingAs($other->user)
            ->get(route('customer.orders.show', $order))
            ->assertForbidden();
        $this->actingAs($other->user)
            ->put(route('customer.addresses.update', $address), [
                'recipient' => 'Intruder',
                'phone' => '080000000000',
                'address' => 'Intruder address',
                'district' => 'Intruder',
                'city' => 'Intruder',
                'province' => 'Intruder',
                'postal_code' => '00000',
            ])
            ->assertForbidden();
        $this->actingAs($other->user)
            ->delete(route('customer.addresses.destroy', $address))
            ->assertForbidden();

        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'recipient' => $address->recipient]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $owner->id]);
    }

    public function test_operator_can_only_open_jobs_assigned_to_them(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $production = $this->makeProduction($order);
        $operator = $this->makeOperator();

        $this->actingAs($operator->user)
            ->get(route('operator.jobs.show', $production))
            ->assertForbidden();

        $production->update(['operator_id' => $operator->id]);
        $this->actingAs($operator->user)
            ->get(route('operator.jobs.show', $production))
            ->assertOk();
    }

    public function test_design_download_is_private_and_limited_to_owner_or_assigned_operator(): void
    {
        Storage::fake('local');
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $order = $this->makeOrder($owner);
        $design = $this->makeDesign($order);
        Storage::disk('local')->put($design->path, 'private design bytes');
        $unassigned = $this->makeOperator();
        $assigned = $this->makeOperator();

        $this->assertTrue(Gate::forUser($owner->user)->allows('view', $design));
        $this->assertFalse(Gate::forUser($other->user)->allows('view', $design));
        $this->assertFalse(Gate::forUser($unassigned->user)->allows('view', $design));
        $this->actingAs($owner->user)
            ->get(route('customer.orders.design-download', [$order, $design]))
            ->assertOk()
            ->assertDownload('design.pdf');
        $this->actingAs($other->user)
            ->get(route('customer.orders.design-download', [$order, $design]))
            ->assertForbidden();

        $this->actingAs($unassigned->user)
            ->get(route('operator.designs.download', $design))
            ->assertForbidden();

        $order->production()->create([
            'operator_id' => $assigned->id,
            'status' => 'IN_DESIGN',
        ]);
        $this->actingAs($assigned->user)
            ->get(route('operator.designs.download', $design))
            ->assertOk();
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.designs.download', $design))
            ->assertOk();
    }

    public function test_unassigned_production_job_is_not_visible_to_an_operator(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $production = $this->makeProduction($order);
        $operator = $this->makeOperator();

        $this->actingAs($operator->user)
            ->get(route('operator.jobs.show', $production))
            ->assertForbidden();

        $this->assertDatabaseHas('production_orders', [
            'id' => $production->id,
            'operator_id' => null,
        ]);
    }
}
