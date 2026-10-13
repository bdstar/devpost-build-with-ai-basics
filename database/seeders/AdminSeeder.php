<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('app.admin');

        if (blank($admin['email']) || blank($admin['password'])) {
            throw new RuntimeException('ADMIN_EMAIL and ADMIN_PASSWORD must be set in .env before seeding.');
        }

        User::updateOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $admin['password']],
        );
    }
}
