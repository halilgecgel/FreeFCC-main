<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Intentionally empty. Create your first admin panel login with:
        //   php artisan make:filament-user
        // Create app members (mobile app accounts) from the admin panel
        // itself, under "Üyeler", once you're logged in.
    }
}
