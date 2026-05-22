<?php

declare(strict_types=1);

namespace App\Filters\Translation;

use App\Filters\Contracts\Filter;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Full-text search over translation content (e.g. ?content=welcome).
 *
 * Uses the InnoDB FULLTEXT index via MATCH ... AGAINST in BOOLEAN mode rather
 * than `LIKE '%term%'`. At 100k+ rows the index turns an otherwise full table
 * scan into a sub-50ms lookup. A trailing `*` enables prefix matching of
 * words; boolean operators are stripped from user input so the query cannot
 * be manipulated.
 */
final class ContentFilter implements Filter
{
    public function apply(Builder $query, mixed $value): void
    {
        $term = $this->sanitize((string) $value);

        if ($term === '') {
            return;
        }

        $query->whereFullText('content', $term.'*', ['mode' => 'boolean']);
    }

    /**
     * Remove boolean full-text operators, collapsing them to spaces so user
     * input is treated purely as search terms.
     */
    private function sanitize(string $value): string
    {
        return trim((string) preg_replace('/[+\-><()~*"@]+/', ' ', $value));
    }
}
