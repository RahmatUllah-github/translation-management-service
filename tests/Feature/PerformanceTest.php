<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    actingAsApiUser();
    $this->locale = Locale::factory()->create(['code' => 'en']);
});

it('lists translations with a constant number of queries (no N+1)', function (): void {
    $tags = Tag::factory()->count(4)->create();
    Translation::factory()->for($this->locale)->count(40)->create()
        ->each(fn (Translation $t) => $t->tags()->attach($tags->random(2)->pluck('id')));

    DB::enableQueryLog();
    $this->getJson('/api/v1/translations?per_page=40')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // translations + eager-loaded locales + eager-loaded tags — a small
    // constant, independent of the number of rows returned.
    expect($queryCount)->toBeLessThan(10);
});

it('exports a large dataset with a bounded query count', function (): void {
    Translation::factory()->for($this->locale)->count(500)->create();

    DB::enableQueryLog();
    $response = $this->getJson('/api/v1/translations/export?locale=en')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($response->json())->toHaveCount(500)
        ->and($queryCount)->toBeLessThan(6);
});

it('serves a warm export from cache without re-querying translations', function (): void {
    Translation::factory()->for($this->locale)->count(100)->create();

    $this->getJson('/api/v1/translations/export?locale=en')->assertOk(); // warm cache

    DB::enableQueryLog();
    $this->getJson('/api/v1/translations/export?locale=en')->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A warm hit only performs the small locale lookup — it never scans the
    // translations table (the payload comes straight from the cache), so the
    // query count is a tiny constant regardless of dataset size.
    expect($queryCount)->toBeLessThan(3);
});
