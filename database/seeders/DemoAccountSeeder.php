<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Operator;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo login accounts for local development and automated tests.
 *
 * These accounts are intentionally separate from the real catalog seeder so the
 * product catalog can be seeded without recreating demo users.
 */
class DemoAccountSeeder extends Seeder
{
    /**
     * @return array{admin?: User, operator?: User, customer?: User}
     */
    public function run(): array
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoAccountSeeder dilewati di production. Buat akun operasional melalui prosedur resmi.');

            return [];
        }

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
}
