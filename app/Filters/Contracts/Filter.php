<?php

declare(strict_types=1);

namespace App\Filters\Contracts;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * A single, composable query constraint. Each filter is stateless and only
 * mutates the query builder it is given — enabling open/closed extension:
 * a new search dimension is a new class, not an edit to existing code.
 */
interface Filter
{
    /**
     * @param  Builder<\App\Models\Translation>  $query
     */
    public function apply(Builder $query, mixed $value): void;
}
