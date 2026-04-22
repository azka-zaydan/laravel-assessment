<?php

namespace Database\Seeders;

use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin user from environment
        $adminEmail = (string) Config::get('app.seed_admin_email', 'admin@example.com');
        $adminPassword = (string) Config::get('app.seed_admin_password', 'Password1!');

        User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => Hash::make($adminPassword),
                'is_admin' => true,
            ]
        );

        // 5 regular users via factory (requires fakerphp/faker — skip in production
        // builds where dev deps are stripped out).
        if (class_exists(FakerFactory::class)) {
            User::factory()->count(5)->create();
        }

        // Restaurant + review + menu data. Idempotent — upserts by zomato_id.
        // Safe to run on every deploy.
        $this->call(RestaurantFixtureSeeder::class);
    }
}
