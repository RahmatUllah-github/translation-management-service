<?php

declare(strict_types=1);

use App\Filters\TranslationFilter;
use App\Models\Translation;

beforeEach(function (): void {
    $this->filter = new TranslationFilter();
});

it('applies no constraints when given no filters', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, []);

    expect($query->toSql())->toBe(Translation::query()->toSql());
});

it('skips empty, null and missing filter values', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, ['key' => '', 'content' => null, 'tags' => []]);

    expect($query->toSql())->toBe(Translation::query()->toSql());
});

it('adds an index-friendly key prefix constraint', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, ['key' => 'home']);

    expect($query->toSql())->toContain('`key` like ?')
        ->and($query->getBindings())->toContain('home%');
});

it('adds a full-text constraint for content search', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, ['content' => 'welcome']);

    expect(strtolower($query->toSql()))->toContain('match');
});

it('adds an existence constraint for tag filtering', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, ['tags' => ['web', 'mobile']]);

    expect(strtolower($query->toSql()))->toContain('exists');
});

it('adds a relationship constraint for the locale filter', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, ['locale' => 'en']);

    expect($query->getBindings())->toContain('en');
});

it('skips content search when the term is only boolean operators', function (): void {
    $query = Translation::query();
    $this->filter->apply($query, ['content' => '+++***>><<']);

    expect($query->toSql())->toBe(Translation::query()->toSql());
});

it('skips tag filtering when no valid tag names remain', function (): void {
    $query = Translation::query();
    // Non-string and empty values are discarded, leaving nothing to filter on.
    $this->filter->apply($query, ['tags' => [123, '', null]]);

    expect($query->toSql())->toBe(Translation::query()->toSql());
});
