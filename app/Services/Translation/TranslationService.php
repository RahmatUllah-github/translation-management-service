<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Filters\TranslationFilter;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Owns translation CRUD and listing/search business rules. Controllers call
 * into this service with already-validated data; the service knows nothing
 * about HTTP, which keeps it fully unit-testable.
 */
final class TranslationService
{
    /**
     * Columns selected for list/show. `id`/`locale_id` are required for the
     * eager-loaded relations; nothing else is fetched, keeping rows lean.
     *
     * @var array<int, string>
     */
    private const COLUMNS = ['id', 'locale_id', 'key', 'content', 'created_at', 'updated_at'];

    public function __construct(private readonly TranslationFilter $filter)
    {
    }

    /**
     * List translations with optional filters, using cursor pagination.
     *
     * Cursor pagination (keyed on the indexed `id`) stays O(page size) at any
     * depth — unlike OFFSET, which scans and discards all skipped rows.
     * Relations are eager-loaded with constrained column lists to avoid N+1.
     *
     * @param  array<string, mixed>  $filters
     * @return CursorPaginator<int, Translation>
     */
    public function paginate(array $filters, int $perPage = 50): CursorPaginator
    {
        $query = Translation::query()
            ->select(self::COLUMNS)
            ->with(['locale:id,code,name', 'tags:id,name'])
            ->orderBy('id');

        $this->filter->apply($query, $filters);

        return $query->cursorPaginate($perPage)->withQueryString();
    }

    /**
     * Load a single translation with its relations.
     */
    public function find(Translation $translation): Translation
    {
        return $translation->load(['locale:id,code,name', 'tags:id,name']);
    }

    /**
     * Create a translation and sync its tags inside one transaction.
     *
     * @param  array<string, mixed>  $data  Validated payload; `locale_id` has
     *                                      already been resolved from `locale`.
     */
    public function create(array $data): Translation
    {
        return DB::transaction(function () use ($data): Translation {
            $translation = Translation::query()->create([
                'locale_id' => $data['locale_id'],
                'key' => $data['key'],
                'content' => $data['content'],
            ]);

            if (! empty($data['tags'])) {
                $translation->tags()->sync($this->resolveTagIds($data['tags']));
            }

            return $this->find($translation);
        });
    }

    /**
     * Update a translation. Only the supplied fields are changed; tags are
     * re-synced only when a `tags` array is present in the payload.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Translation $translation, array $data): Translation
    {
        return DB::transaction(function () use ($translation, $data): Translation {
            $attributes = Arr::only($data, ['locale_id', 'key', 'content']);

            if ($attributes !== []) {
                $translation->fill($attributes)->save();
            }

            if (array_key_exists('tags', $data)) {
                $translation->tags()->sync($this->resolveTagIds((array) $data['tags']));
            }

            return $this->find($translation->refresh());
        });
    }

    /**
     * Delete a translation. The pivot rows cascade at the database level.
     */
    public function delete(Translation $translation): void
    {
        $translation->delete();
    }

    /**
     * Resolve tag names to ids, creating any that do not yet exist.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    private function resolveTagIds(array $names): array
    {
        $names = array_values(array_unique(array_filter(
            $names,
            static fn (mixed $name): bool => is_string($name) && trim($name) !== '',
        )));

        if ($names === []) {
            return [];
        }

        $existing = Tag::query()->whereIn('name', $names)->pluck('id', 'name');

        $missing = array_diff($names, $existing->keys()->all());

        foreach ($missing as $name) {
            $existing[$name] = Tag::query()->create(['name' => $name])->id;
        }

        return array_values($existing->all());
    }
}
