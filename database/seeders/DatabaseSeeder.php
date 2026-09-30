<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Seeds the master data reference tables, then creates a development
     * administrator with a well known password, so it must never run outside
     * of a local environment. Real accounts are created by an administrator
     * from within the application.
     */
    public function run(): void
    {
        $this->call([
            SchoolClassSeeder::class,
            CateringCategorySeeder::class,
            CateringMemberSeeder::class,
        ]);

        if (app()->isProduction()) {
            $this->command?->warn('Skipping the development administrator in production.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
            ],
        );
    }
}
