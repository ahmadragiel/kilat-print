<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoAccountSeeder;
use Tests\TestCase;

/**
 * Demo logins use a known password and must never be created in production,
 * even if someone runs DatabaseSeeder there by mistake.
 */
class DemoAccountSeederTest extends TestCase
{
    public function test_demo_accounts_are_skipped_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $seeder = new DemoAccountSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();

        $this->assertSame(0, User::count());
    }

    public function test_demo_accounts_are_created_outside_production(): void
    {
        $this->seed(DemoAccountSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@kilatprint.test',
            'role' => 'admin',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'operator@kilatprint.test',
            'role' => 'operator',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'customer@kilatprint.test',
            'role' => 'customer',
        ]);
    }
}
