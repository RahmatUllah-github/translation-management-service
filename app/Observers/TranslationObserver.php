<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Translation;
use App\Services\Translation\TranslationExportService;

/**
 * Keeps the export cache fresh. Centralising invalidation in an observer
 * means every write path — service, console, future code — invalidates
 * correctly without having to remember to.
 *
 * Note: bulk inserts (the 100k seeder) intentionally bypass model events for
 * speed; the seeder flushes the relevant versions itself.
 */
final class TranslationObserver
{
    public function __construct(private readonly TranslationExportService $exportService)
    {
    }

    /**
     * Fires after both create and update.
     */
    public function saved(Translation $translation): void
    {
        // If the locale was reassigned, the previous locale's export is stale too.
        if ($translation->wasChanged('locale_id')) {
            $original = $translation->getOriginal('locale_id');

            if ($original !== null) {
                $this->exportService->invalidate((int) $original);
            }
        }

        $this->exportService->invalidate($translation->locale_id);
    }

    public function deleted(Translation $translation): void
    {
        $this->exportService->invalidate($translation->locale_id);
    }
}
