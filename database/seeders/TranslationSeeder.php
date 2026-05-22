<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * Seeds a small, realistic demo set so the API is usable immediately after
 * `migrate --seed`. Large-volume data is handled by `translations:seed`.
 */
final class TranslationSeeder extends Seeder
{
    public function run(): void
    {
        $keys = [
            'homepage.title' => 'Welcome',
            'homepage.subtitle' => 'Manage your translations with ease',
            'auth.login' => 'Log in',
            'auth.logout' => 'Log out',
            'auth.register' => 'Create an account',
            'nav.dashboard' => 'Dashboard',
            'nav.settings' => 'Settings',
            'errors.not_found' => 'The requested page could not be found',
        ];

        $locales = Locale::query()->get();
        $tagIds = Tag::query()->pluck('id');

        foreach ($locales as $locale) {
            foreach ($keys as $key => $value) {
                $translation = Translation::query()->updateOrCreate(
                    ['locale_id' => $locale->id, 'key' => $key],
                    ['content' => "[{$locale->code}] {$value}"],
                );

                $translation->tags()->sync(
                    $tagIds->random(min(2, $tagIds->count()))->all(),
                );
            }
        }
    }
}
