<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Locale;
use Illuminate\Database\Seeder;

final class LocaleSeeder extends Seeder
{
    public function run(): void
    {
        $locales = [
            ['code' => 'en', 'name' => 'English'],
            ['code' => 'fr', 'name' => 'French'],
            ['code' => 'es', 'name' => 'Spanish'],
            ['code' => 'de', 'name' => 'German'],
            ['code' => 'ar', 'name' => 'Arabic'],
            ['code' => 'ur', 'name' => 'Urdu'],
            ['code' => 'ps', 'name' => 'Pashto'],
        ];

        foreach ($locales as $locale) {
            // Idempotent: safe to re-run without duplicating rows.
            Locale::query()->updateOrCreate(
                ['code' => $locale['code']],
                ['name' => $locale['name'], 'is_active' => true],
            );
        }
    }
}
