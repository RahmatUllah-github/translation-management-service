<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Database\Seeders\LocaleSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('seeds the baseline locales', function (): void {
    $this->seed(LocaleSeeder::class);

    expect(Locale::query()->count())->toBe(5)
        ->and(Locale::query()->where('code', 'en')->exists())->toBeTrue();
});

it('seeds the baseline tags', function (): void {
    $this->seed(TagSeeder::class);

    expect(Tag::query()->count())->toBe(5);
});

it('seeders are idempotent when run twice', function (): void {
    $this->seed(LocaleSeeder::class);
    $this->seed(LocaleSeeder::class);

    expect(Locale::query()->count())->toBe(5);
});

it('generates the requested volume via the scalability command', function (): void {
    $this->seed([LocaleSeeder::class, TagSeeder::class]);

    $this->artisan('translations:seed', ['--count' => 200, '--chunk' => 50])
        ->assertSuccessful();

    expect(Translation::query()->count())->toBe(200)
        ->and(DB::table('tag_translation')->count())->toBeGreaterThanOrEqual(200);
});

it('fails fast when locales or tags are missing', function (): void {
    $this->artisan('translations:seed', ['--count' => 10])
        ->assertFailed();
});
