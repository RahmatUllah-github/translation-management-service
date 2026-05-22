<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes the tag <-> translation relationship', function (): void {
    $tag = Tag::factory()->create();
    $translation = Translation::factory()->for(Locale::factory())->create();

    $tag->translations()->attach($translation->id);

    expect($tag->translations)->toHaveCount(1)
        ->and($tag->translations->first()->is($translation))->toBeTrue();
});

it('exposes the locale -> translations relationship', function (): void {
    $locale = Locale::factory()->create();
    Translation::factory()->for($locale)->count(2)->create();

    expect($locale->translations)->toHaveCount(2);
});

it('exposes the translation -> locale relationship', function (): void {
    $locale = Locale::factory()->create();
    $translation = Translation::factory()->for($locale)->create();

    expect($translation->locale->is($locale))->toBeTrue();
});

it('restricts the active scope to enabled locales', function (): void {
    Locale::factory()->count(2)->create(['is_active' => true]);
    Locale::factory()->inactive()->create();

    expect(Locale::query()->active()->count())->toBe(2);
});
