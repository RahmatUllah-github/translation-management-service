<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for cache keys. Centralising them keeps the key
 * scheme consistent and makes the versioned-invalidation strategy auditable.
 */
final class CacheKeys
{
    /**
     * Counter incremented on every write affecting a locale's export.
     */
    public static function exportVersion(int $localeId): string
    {
        return "translations:export:version:locale:{$localeId}";
    }

    /**
     * Versioned payload key — a new version yields a new key, so stale
     * payloads are simply orphaned (and TTL-expired) rather than read.
     */
    public static function exportPayload(int $localeId, int $version): string
    {
        return "translations:export:payload:locale:{$localeId}:v{$version}";
    }
}
