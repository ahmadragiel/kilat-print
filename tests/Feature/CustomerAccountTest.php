<?php

namespace Tests\Feature;

use App\Notifications\OrderNotification;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerAccountTest extends TestCase
{
    public function test_customer_can_view_and_update_profile_including_password(): void
    {
        $customer = $this->makeCustomer(['name' => 'Old Name', 'email' => 'old-profile@example.test']);

        $this->actingAs($customer->user)
            ->get(route('customer.profile.edit'))
            ->assertOk();

        $this->actingAs($customer->user)
            ->put(route('customer.profile.update'), [
                'name' => 'New Name',
                'email' => 'new-profile@example.test',
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHas('success');

        $customer->user->refresh();
        $this->assertSame('New Name', $customer->user->name);
        $this->assertSame('new-profile@example.test', $customer->user->email);
        $this->assertTrue(Hash::check('new-password-123', $customer->user->password));
    }

    public function test_customer_notifications_can_be_viewed_and_marked_read_individually_or_all_at_once(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $customer->user->notify(new OrderNotification(
            'Order update',
            'Your order has a new update.',
            route('customer.orders.show', $order),
        ));
        $notification = $customer->user->notifications()->firstOrFail();

        $this->actingAs($customer->user)
            ->get(route('customer.notifications.index'))
            ->assertOk()
            ->assertSee('Order update')
            ->assertSee('Your order has a new update.');

        $this->actingAs($customer->user)
            ->get(route('customer.notifications.read', $notification->id))
            ->assertRedirect(route('customer.orders.show', $order));
        $this->assertNotNull($notification->fresh()->read_at);

        $customer->user->notify(new OrderNotification('Second update', 'Another update.', route('customer.dashboard')));
        $this->actingAs($customer->user)
            ->post(route('customer.notifications.read-all'))
            ->assertSessionHas('success');
        $this->assertSame(0, $customer->user->unreadNotifications()->count());
    }

    public function test_addresses_are_created_and_only_one_remains_primary(): void
    {
        $customer = $this->makeCustomer();
        $payload = [
            'recipient' => 'Address Owner',
            'phone' => '081200000001',
            'address' => 'Jalan_ADDRESS No. 1',
            'district' => 'District',
            'city' => 'City',
            'province' => 'Province',
            'postal_code' => '12345',
        ];

        $this->actingAs($customer->user)
            ->post(route('customer.addresses.store'), $payload)
            ->assertSessionHas('success');
        $first = $customer->addresses()->firstOrFail();
        $this->assertTrue($first->is_primary);

        $this->actingAs($customer->user)
            ->post(route('customer.addresses.store'), array_merge($payload, [
                'recipient' => 'Second Address',
                'is_primary' => true,
            ]))
            ->assertSessionHas('success');
        $second = $customer->addresses()->where('recipient', 'Second Address')->firstOrFail();
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertFalse($first->fresh()->is_primary);

        $this->actingAs($customer->user)
            ->post(route('customer.addresses.primary', $first))
            ->assertSessionHas('success');
        $this->assertTrue($first->fresh()->is_primary);
        $this->assertFalse($second->fresh()->is_primary);
        $this->assertSame(1, $customer->addresses()->where('is_primary', true)->count());

        $this->actingAs($customer->user)
            ->get(route('customer.addresses.index'))
            ->assertOk()
            ->assertSee('Second Address');
    }

    public function test_customer_address_update_and_delete_are_scoped_to_owner(): void
    {
        $owner = $this->makeCustomer();
        $other = $this->makeCustomer();
        $address = $this->makeAddress($owner);

        $this->actingAs($other->user)
            ->put(route('customer.addresses.update', $address), [
                'recipient' => 'Stolen',
                'phone' => '080000000000',
                'address' => 'Stolen address',
                'district' => 'Stolen',
                'city' => 'Stolen',
                'province' => 'Stolen',
                'postal_code' => '00000',
            ])
            ->assertForbidden();
        $this->actingAs($other->user)
            ->delete(route('customer.addresses.destroy', $address))
            ->assertForbidden();

        $this->actingAs($owner->user)
            ->put(route('customer.addresses.update', $address), [
                'recipient' => 'Updated Owner',
                'phone' => '081200000009',
                'address' => 'Updated address',
                'district' => 'Updated District',
                'city' => 'Updated City',
                'province' => 'Updated Province',
                'postal_code' => '54321',
            ])
            ->assertSessionHas('success');
        $this->assertSame('Updated Owner', $address->fresh()->recipient);
    }
}
