<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Translation;
use App\Services\Translation\TranslationExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(TranslationExportService::class);
});

it('initialises a locale export version to 1', function (): void {
    $locale = Locale::factory()->create();

    expect($this->service->version($locale->id))->toBe(1);
});

it('increments the version when invalidated', function (): void {
    $locale = Locale::factory()->create();
    $this->service->version($locale->id);

    $this->service->invalidate($locale->id);

    expect($this->service->version($locale->id))->toBe(2);
});

it('builds a flat key/value JSON map', function (): void {
    $locale = Locale::factory()->create();
    Translation::factory()->for($locale)->create(['key' => 'homepage.title', 'content' => 'Welcome']);
    Translation::factory()->for($locale)->create(['key' => 'auth.login', 'content' => 'Log in']);

    $payload = json_decode($this->service->exportJson($locale), true);

    expect($payload)->toBe(['auth.login' => 'Log in', 'homepage.title' => 'Welcome']);
});

it('returns an empty JSON object for a locale with no translations', function (): void {
    $locale = Locale::factory()->create();

    expect($this->service->exportJson($locale))->toBe('{}');
});

it('serves a fresh payload after invalidation (no stale cache)', function (): void {
    $locale = Locale::factory()->create();
    $translation = Translation::factory()->for($locale)->create(['key' => 'k', 'content' => 'old']);

    $this->service->exportJson($locale); // warm the cache

    $translation->update(['content' => 'new']); // observer invalidates

    expect(json_decode($this->service->exportJson($locale), true))->toBe(['k' => 'new']);
});

it('invalidates the previous locale when a translation is reassigned', function (): void {
    $en = Locale::factory()->create();
    $fr = Locale::factory()->create();
    $translation = Translation::factory()->for($en)->create();

    // Warm both version counters so an increment is observable.
    $enBefore = $this->service->version($en->id);
    $frBefore = $this->service->version($fr->id);

    // Reassigning the locale must invalidate BOTH the old and the new locale.
    $translation->update(['locale_id' => $fr->id]);

    expect($this->service->version($en->id))->toBeGreaterThan($enBefore)
        ->and($this->service->version($fr->id))->toBeGreaterThan($frBefore);
});

it('isolates export caches per locale', function (): void {
    $en = Locale::factory()->create();
    $fr = Locale::factory()->create();
    Translation::factory()->for($en)->create(['key' => 'k', 'content' => 'English']);
    Translation::factory()->for($fr)->create(['key' => 'k', 'content' => 'French']);

    expect(json_decode($this->service->exportJson($en), true))->toBe(['k' => 'English'])
        ->and(json_decode($this->service->exportJson($fr), true))->toBe(['k' => 'French']);
});
