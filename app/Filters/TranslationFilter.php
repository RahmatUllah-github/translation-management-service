<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Contracts\Filter;
use App\Filters\Translation\ContentFilter;
use App\Filters\Translation\KeyFilter;
use App\Filters\Translation\LocaleFilter;
use App\Filters\Translation\TagsFilter;
use App\Models\Translation;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Applies the set of supported translation filters to a query.
 *
 * The param => filter map is the single extension point: adding a search
 * dimension means adding one entry and one Filter class, with no change to
 * the calling service or controller (open/closed principle).
 */
final class TranslationFilter
{
    /**
     * @var array<string, class-string<Filter>>
     */
    private const FILTERS = [
        'locale' => LocaleFilter::class,
        'key' => KeyFilter::class,
        'content' => ContentFilter::class,
        'tags' => TagsFilter::class,
    ];

    /**
     * @param  Builder<Translation>  $query
     * @param  array<string, mixed>  $filters
     */
    public function apply(Builder $query, array $filters): void
    {
        foreach (self::FILTERS as $param => $filterClass) {
            $value = $filters[$param] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            (new $filterClass())->apply($query, $value);
        }
    }
}
