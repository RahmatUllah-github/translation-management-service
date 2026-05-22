<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    actingAsApiUser();
    $this->locale = Locale::factory()->create(['code' => 'en']);
});

it('creates a translation with tags', function (): void {
    $this->postJson('/api/v1/translations', [
        'locale' => 'en',
        'key' => 'homepage.title',
        'content' => 'Welcome',
        'tags' => ['web', 'mobile'],
    ])
        ->assertCreated()
        ->assertJsonPath('data.key', 'homepage.title')
        ->assertJsonPath('data.locale.code', 'en')
        ->assertJsonCount(2, 'data.tags');

    $this->assertDatabaseHas('translations', [
        'key' => 'homepage.title',
        'content' => 'Welcome',
    ]);
});

it('shows a single translation', function (): void {
    $translation = Translation::factory()->for($this->locale)->create();

    $this->getJson("/api/v1/translations/{$translation->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $translation->id);
});

it('updates a translation', function (): void {
    $translation = Translation::factory()->for($this->locale)->create(['content' => 'old']);

    $this->patchJson("/api/v1/translations/{$translation->id}", ['content' => 'new'])
        ->assertOk()
        ->assertJsonPath('data.content', 'new');

    $this->assertDatabaseHas('translations', ['id' => $translation->id, 'content' => 'new']);
});

it('deletes a translation', function (): void {
    $translation = Translation::factory()->for($this->locale)->create();

    $this->deleteJson("/api/v1/translations/{$translation->id}")->assertOk();

    $this->assertDatabaseMissing('translations', ['id' => $translation->id]);
});

it('reassigns a translation to a different locale', function (): void {
    $fr = Locale::factory()->create(['code' => 'fr']);
    $translation = Translation::factory()->for($this->locale)->create();

    $this->patchJson("/api/v1/translations/{$translation->id}", ['locale' => 'fr'])
        ->assertOk()
        ->assertJsonPath('data.locale.code', 'fr');

    $this->assertDatabaseHas('translations', [
        'id' => $translation->id,
        'locale_id' => $fr->id,
    ]);
});

it('returns a clean 404 for a missing translation', function (): void {
    $this->getJson('/api/v1/translations/999999')
        ->assertNotFound()
        ->assertExactJson([
            'status' => 'failed',
            'code' => 404,
            'message' => 'Resource not found.',
            'data' => null,
        ]);
});

it('cascades pivot rows when a translation is deleted', function (): void {
    $translation = Translation::factory()->for($this->locale)->create();
    $translation->tags()->attach(Tag::factory()->create()->id);

    $this->deleteJson("/api/v1/translations/{$translation->id}")->assertOk();

    $this->assertDatabaseMissing('tag_translation', ['translation_id' => $translation->id]);
});
