<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    actingAsApiUser();
    $this->locale = Locale::factory()->create(['code' => 'en']);
});

it('exports translations as a flat key/value map', function (): void {
    Translation::factory()->for($this->locale)->create(['key' => 'homepage.title', 'content' => 'Welcome']);
    Translation::factory()->for($this->locale)->create(['key' => 'auth.login', 'content' => 'Log in']);

    $response = $this->getJson('/api/v1/translations/export?locale=en')->assertOk();

    expect($response->json())->toBe([
        'auth.login' => 'Log in',
        'homepage.title' => 'Welcome',
    ]);
});

it('always returns the latest data after an update', function (): void {
    $translation = Translation::factory()->for($this->locale)->create(['key' => 'k', 'content' => 'before']);

    $this->getJson('/api/v1/translations/export?locale=en')
        ->assertJsonPath('k', 'before');

    $this->patchJson("/api/v1/translations/{$translation->id}", ['content' => 'after']);

    $this->getJson('/api/v1/translations/export?locale=en')
        ->assertJsonPath('k', 'after');
});

it('returns 304 when the client ETag still matches', function (): void {
    Translation::factory()->for($this->locale)->create();

    $etag = $this->getJson('/api/v1/translations/export?locale=en')
        ->assertOk()
        ->headers->get('ETag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->getJson('/api/v1/translations/export?locale=en')
        ->assertStatus(304);
});

it('changes the ETag after the dataset changes', function (): void {
    Translation::factory()->for($this->locale)->create();

    $first = $this->getJson('/api/v1/translations/export?locale=en')->headers->get('ETag');

    Translation::factory()->for($this->locale)->create();

    $second = $this->getJson('/api/v1/translations/export?locale=en')->headers->get('ETag');

    expect($first)->not->toBe($second);
});

it('rejects an export request for an unknown locale', function (): void {
    $this->getJson('/api/v1/translations/export?locale=zz')
        ->assertStatus(422)
        ->assertJsonValidationErrors('locale', 'data');
});
