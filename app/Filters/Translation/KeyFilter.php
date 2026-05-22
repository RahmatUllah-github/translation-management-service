<?php

declare(strict_types=1);

namespace App\Filters\Translation;

use App\Filters\Contracts\Filter;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filters translations by key (e.g. ?key=homepage).
 *
 * A prefix match (`LIKE 'homepage%'`) is used deliberately: it can be served
 * by `translations_key_index`, whereas a leading-wildcard `LIKE '%homepage%'`
 * would force a full table scan at 100k+ rows. Dotted keys make prefix search
 * natural — `homepage` matches `homepage.title`, `homepage.cta`, etc.
 */
final class KeyFilter implements Filter
{
    public function apply(Builder $query, mixed $value): void
    {
        $query->where('key', 'like', $this->escapeLike((string) $value).'%');
    }

    /**
     * Escape LIKE wildcards so user input is treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
