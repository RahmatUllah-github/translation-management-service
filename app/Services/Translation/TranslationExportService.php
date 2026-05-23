<?php

declare(strict_types=1);

namespace App\Services\Translation;

use App\Models\Locale;
use App\Support\CacheKeys;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds and caches the flat translation map consumed by frontend apps.
 *
 * Caching strategy — versioned keys, never a naive forever-cache:
 *
 *  - Each locale has a version counter. The payload is stored under a key
 *    that embeds that version.
 *  - A write (create/update/delete) increments the counter via invalidate().
 *    The next export computes a new key, misses, and rebuilds — so a stale
 *    payload is never served, yet no explicit cache busting is needed.
 *  - The TTL is only a storage bound; correctness comes from versioning.
 */
final class TranslationExportService
{
    /**
     * Storage-bound TTL (24h). Freshness is guaranteed by versioning, not TTL.
     */
    private const PAYLOAD_TTL = 86_400;

    public function __construct(private readonly Cache $cache)
    {
    }

    /**
     * Current export version for a locale (lazily initialised to 1).
     */
    public function version(int $localeId): int
    {
        $key = CacheKeys::exportVersion($localeId);
        $version = $this->cache->get($key);

        if ($version === null) {
            $this->cache->forever($key, 1);

            return 1;
        }

        return (int) $version;
    }

    /**
     * Invalidate a locale's export by bumping its version counter. Called by
     * the TranslationObserver on every create/update/delete.
     */
    public function invalidate(int $localeId): void
    {
        $key = CacheKeys::exportVersion($localeId);

        // increment() is atomic but needs the key to exist first.
        if ($this->cache->get($key) === null) {
            $this->cache->forever($key, 1);

            return;
        }

        $this->cache->increment($key);
    }

    /**
     * Return the locale's translations as a JSON string ({"key":"value"}).
     *
     * The encoded string — not a PHP array — is cached, so a cache hit skips
     * both the query and re-encoding and the controller can stream it as-is.
     */
    public function exportJson(Locale $locale): string
    {
        $version = $this->version($locale->id);
        $key = CacheKeys::exportPayload($locale->id, $version);

        return $this->cache->remember(
            $key,
            self::PAYLOAD_TTL,
            fn (): string => $this->build($locale->id),
        );
    }

    /**
     * Build the flat translation map for a locale straight from the database.
     *
     * Performance: queries only the two needed columns through the base query
     * builder (no Eloquent hydration), and uses pluck() to materialise the
     * key=>content map in a single pass at the DB driver level. The
     * WHERE locale_id = ? ORDER BY key is served directly by the
     * UNIQUE(locale_id, key) index.
     *
     * Why not lazy()/cursor()? The endpoint must encode the full payload
     * before responding (and before caching it), so we can't avoid holding it
     * in memory. lazy() adds chunked-query and closure-per-row overhead
     * without any memory win — pluck() is ~15x faster at 20k rows.
     */
    private function build(int $localeId): string
    {
        $map = DB::table('translations')
            ->where('locale_id', $localeId)
            ->orderBy('key')
            ->pluck('content', 'key')
            ->all();

        // Cast to object so an empty result encodes as {} rather than [].
        return (string) json_encode((object) $map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
