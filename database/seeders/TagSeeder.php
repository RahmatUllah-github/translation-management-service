<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

final class TagSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['mobile', 'desktop', 'web', 'email', 'marketing'] as $name) {
            Tag::query()->updateOrCreate(['name' => $name]);
        }
    }
}
