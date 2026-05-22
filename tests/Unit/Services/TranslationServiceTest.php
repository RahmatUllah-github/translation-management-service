<?php

declare(strict_types=1);

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Services\Translation\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(TranslationService::class);
    $this->locale = Locale::factory()->create(['code' => 'en']);
});

it('creates a translation with its tags', function (): void {
    $translation = $this->service->create([
        'locale_id' => $this->locale->id,
        'key' => 'homepage.title',
        'content' => 'Welcome',
        'tags' => ['web', 'mobile'],
    ]);

    expect($translation->key)->toBe('homepage.title')
        ->and($translation->tags)->toHaveCount(2);

    $this->assertDatabaseHas('translations', ['key' => 'homepage.title']);
});

it('reuses existing tags and creates only missing ones', function (): void {
    Tag::factory()->create(['name' => 'web']);

    $this->service->create([
        'locale_id' => $this->locale->id,
        'key' => 'k',
        'content' => 'c',
        'tags' => ['web', 'brand-new'],
    ]);

    expect(Tag::query()->count())->toBe(2);
});

it('updates only the supplied fields', function (): void {
    $translation = Translation::factory()->for($this->locale)->create([
        'key' => 'original.key',
        'content' => 'original',
    ]);

    $updated = $this->service->update($translation, ['content' => 'changed']);

    expect($updated->content)->toBe('changed')
        ->and($updated->key)->toBe('original.key');
});

it('re-syncs tags on update when a tags array is provided', function (): void {
    $translation = Translation::factory()->for($this->locale)->create();
    $translation->tags()->attach(Tag::factory()->create()->id);

    $updated = $this->service->update($translation, ['tags' => ['only-this']]);

    expect($updated->tags)->toHaveCount(1)
        ->and($updated->tags->first()->name)->toBe('only-this');
});

it('leaves tags untouched on update when no tags key is present', function (): void {
    $translation = Translation::factory()->for($this->locale)->create();
    $translation->tags()->attach(Tag::factory()->count(2)->create()->pluck('id'));

    $updated = $this->service->update($translation, ['content' => 'changed']);

    expect($updated->tags)->toHaveCount(2);
});

it('deletes a translation', function (): void {
    $translation = Translation::factory()->for($this->locale)->create();

    $this->service->delete($translation);

    $this->assertDatabaseMissing('translations', ['id' => $translation->id]);
});

it('paginates results and honours filters', function (): void {
    Translation::factory()->for($this->locale)->count(5)
        ->sequence(fn ($sequence): array => ['key' => "kept.key.{$sequence->index}"])
        ->create();
    Translation::factory()->for($this->locale)->count(5)
        ->sequence(fn ($sequence): array => ['key' => "other.key.{$sequence->index}"])
        ->create();

    $page = $this->service->paginate(['key' => 'kept'], perPage: 3);

    expect($page->items())->toHaveCount(3)
        ->and($page->hasMorePages())->toBeTrue();
});
