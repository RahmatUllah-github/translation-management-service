<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locale;
use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Translation>
 */
final class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Creates a locale unless the caller supplies one via ->for()/->recycle().
            'locale_id' => Locale::factory(),
            'key' => str_replace('-', '.', fake()->unique()->slug(3)),
            'content' => fake()->sentence(),
        ];
    }
}
