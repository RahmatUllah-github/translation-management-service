<?php

declare(strict_types=1);

namespace App\Filters\Translation;

use App\Filters\Contracts\Filter;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Filters translations by their locale code (e.g. ?locale=en).
 *
 * Uses a relationship existence constraint which the optimiser resolves as a
 * semi-join; translations.locale_id is indexed (leading column of the
 * composite unique), so the lookup stays cheap at scale.
 */
final class LocaleFilter implements Filter
{
    public function apply(Builder $query, mixed $value): void
    {
        $query->whereRelation('locale', 'code', (string) $value);
    }
}
