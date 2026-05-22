<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\DatabaseMigrations;

/*
 * DatabaseMigrations (not RefreshDatabase) is used here on purpose: the
 * content filter relies on an InnoDB FULLTEXT index, which does not return
 * rows inserted inside the still-open transaction that RefreshDatabase keeps.
 */
uses(DatabaseMigrations::class);

beforeEach(function (): void {
    actingAsApiUser();
    $this->en = Locale::factory()->create(['code' => 'en']);
    $this->fr = Locale::factory()->create(['code' => 'fr']);
});

it('filters by locale', function (): void {
    Translation::factory()->for($this->en)->create();
    Translation::factory()->for($this->fr)->create();

    $this->getJson('/api/v1/translations?locale=fr')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.locale.code', 'fr');
});

it('filters by key prefix', function (): void {
    Translation::factory()->for($this->en)->create(['key' => 'homepage.title']);
    Translation::factory()->for($this->en)->create(['key' => 'auth.login']);

    $this->getJson('/api/v1/translations?key=homepage')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.key', 'homepage.title');
});

it('filters by content using full-text search', function (): void {
    Translation::factory()->for($this->en)->create(['key' => 'a', 'content' => 'Welcome aboard friend']);
    Translation::factory()->for($this->en)->create(['key' => 'b', 'content' => 'Goodbye everyone']);

    $this->getJson('/api/v1/translations?content=welcome')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.key', 'a');
});

it('filters by tag with any-match semantics', function (): void {
    $tagged = Translation::factory()->for($this->en)->create();
    $tagged->tags()->attach(Tag::factory()->create(['name' => 'mobile'])->id);
    Translation::factory()->for($this->en)->create();

    $this->getJson('/api/v1/translations?tags[]=mobile')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('combines multiple filters with AND', function (): void {
    Translation::factory()->for($this->en)->create(['key' => 'homepage.title']);
    Translation::factory()->for($this->fr)->create(['key' => 'homepage.title']);
    Translation::factory()->for($this->en)->create(['key' => 'other.key']);

    $this->getJson('/api/v1/translations?locale=en&key=homepage')
        ->assertOk()
        ->assertJsonCount(1, 'data.data');
});

it('cursor-paginates the result set', function (): void {
    Translation::factory()->for($this->en)->count(5)->create();

    $response = $this->getJson('/api/v1/translations?per_page=2')->assertOk();

    expect($response->json('data.data'))->toHaveCount(2)
        ->and($response->json('data.meta.next_cursor'))->not->toBeNull();
});

it('rejects an out-of-range page size', function (): void {
    $this->getJson('/api/v1/translations?per_page=9999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('per_page', 'data');
});
