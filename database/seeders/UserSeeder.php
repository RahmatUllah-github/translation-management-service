<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Default API user — credentials documented in the README.
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'API Admin', 'password' => Hash::make('password')],
        );
    }
}
