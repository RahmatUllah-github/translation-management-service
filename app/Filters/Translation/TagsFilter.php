<?php

declare(strict_types=1);

namespace App\Filters\Translation;

use App\Filters\Contracts\Filter;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filters translations by tag name(s) (e.g. ?tags[]=mobile&tags[]=web).
 *
 * ANY-match semantics: a translation is returned if it carries at least one
 * of the requested tags. The existence subquery is served by the pivot's
 * (tag_id, translation_id) index plus the unique tags.name index.
 */
final class TagsFilter implements Filter
{
    public function apply(Builder $query, mixed $value): void
    {
        $tags = array_values(array_filter(
            (array) $value,
            static fn (mixed $tag): bool => is_string($tag) && $tag !== '',
        ));

        if ($tags === []) {
            return;
        }

        $query->whereHas('tags', static fn (Builder $tagQuery) => $tagQuery->whereIn('name', $tags));
    }
}
