<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->locale = Locale::factory()->create(['code' => 'en']);
});

it('requires authentication for translation endpoints', function (): void {
    $this->getJson('/api/v1/translations')->assertUnauthorized();
    $this->postJson('/api/v1/translations', [])->assertUnauthorized();
});

it('rejects creation with missing required fields', function (): void {
    actingAsApiUser();

    $this->postJson('/api/v1/translations', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale', 'key', 'content'], 'data');
});

it('rejects an unknown locale', function (): void {
    actingAsApiUser();

    $this->postJson('/api/v1/translations', [
        'locale' => 'zz',
        'key' => 'a.b',
        'content' => 'c',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('locale', 'data');
});

it('rejects a duplicate key within the same locale', function (): void {
    actingAsApiUser();
    Translation::factory()->for($this->locale)->create(['key' => 'homepage.title']);

    $this->postJson('/api/v1/translations', [
        'locale' => 'en',
        'key' => 'homepage.title',
        'content' => 'Duplicate',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('key', 'data');
});

it('allows the same key across different locales', function (): void {
    actingAsApiUser();
    $fr = Locale::factory()->create(['code' => 'fr']);
    Translation::factory()->for($this->locale)->create(['key' => 'shared.key']);

    $this->postJson('/api/v1/translations', [
        'locale' => 'fr',
        'key' => 'shared.key',
        'content' => 'Bonjour',
    ])->assertCreated();

    expect($fr->translations()->count())->toBe(1);
});

it('rejects an over-long key', function (): void {
    actingAsApiUser();

    $this->postJson('/api/v1/translations', [
        'locale' => 'en',
        'key' => str_repeat('x', 192),
        'content' => 'c',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('key', 'data');
});

it('lets a translation keep its own key on update', function (): void {
    actingAsApiUser();
    $translation = Translation::factory()->for($this->locale)->create(['key' => 'keep.key']);

    $this->patchJson("/api/v1/translations/{$translation->id}", [
        'key' => 'keep.key',
        'content' => 'updated',
    ])->assertOk();
});
