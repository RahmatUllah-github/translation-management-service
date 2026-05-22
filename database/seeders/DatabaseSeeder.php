<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed a fully usable baseline: an API user, locales, tags and a small
     * demo set of translations. Use `php artisan translations:seed` to load
     * the 100k+ dataset for performance testing.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            LocaleSeeder::class,
            TagSeeder::class,
            TranslationSeeder::class,
        ]);
    }
}
