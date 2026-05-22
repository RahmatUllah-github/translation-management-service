<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use App\Services\Translation\TranslationExportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates a large volume of translations for scalability/performance
 * testing (default 100k+).
 *
 * Performance choices:
 *  - Bulk `insert()` via the query builder — no Eloquent hydration and no
 *    model events fired (the per-row observer would be catastrophic here).
 *  - Inserts are chunked to bound memory regardless of total count.
 *  - Tag attachment streams the *actual* inserted ids (auto-increment values
 *    cannot be assumed contiguous), so it stays correct after rollbacks.
 *  - Export caches are invalidated once at the end, not per row.
 */
final class SeedTranslationsCommand extends Command
{
    protected $signature = 'translations:seed
        {--count=100000 : Number of translation rows to generate}
        {--chunk=2000 : Rows per bulk insert}';

    protected $description = 'Generate a large volume of translations for performance testing';

    public function handle(TranslationExportService $exporter): int
    {
        $count = max(1, (int) $this->option('count'));
        $chunk = max(100, (int) $this->option('chunk'));

        $localeIds = Locale::query()->pluck('id')->all();
        $tagIds = Tag::query()->pluck('id')->all();

        if ($localeIds === [] || $tagIds === []) {
            $this->components->error('Seed locales and tags first:  php artisan db:seed');

            return self::FAILURE;
        }

        // Everything inserted by this run will have id > $afterId.
        $afterId = (int) (Translation::query()->max('id') ?? 0);

        $this->components->info(sprintf('Generating %s translations...', number_format($count)));
        $this->insertTranslations($afterId, $count, $chunk, $localeIds);

        $this->components->info('Attaching tags...');
        $this->attachTags($afterId, $tagIds, $chunk);

        // Invalidate every locale's export once — cheap, and keeps the cache
        // strategy consistent with the normal write paths.
        foreach ($localeIds as $localeId) {
            $exporter->invalidate((int) $localeId);
        }

        $this->components->info(sprintf(
            'Done. translations table now holds %s rows.',
            number_format(Translation::query()->count()),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $localeIds
     */
    private function insertTranslations(int $afterId, int $count, int $chunk, array $localeIds): void
    {
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $now = now();
        $buffer = [];

        for ($i = $afterId + 1; $i <= $afterId + $count; $i++) {
            $buffer[] = [
                'locale_id' => $localeIds[array_rand($localeIds)],
                // `$i` guarantees global key uniqueness, so the
                // UNIQUE(locale_id, key) constraint is never violated.
                'key' => sprintf('module_%d.section_%d.key_%d', intdiv($i, 5_000), intdiv($i, 100), $i),
                'content' => 'Translation content #'.$i.' '.Str::random(16),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($buffer) === $chunk) {
                DB::table('translations')->insert($buffer);
                $bar->advance($chunk);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('translations')->insert($buffer);
            $bar->advance(count($buffer));
        }

        $bar->finish();
        $this->newLine(2);
    }

    /**
     * Attach 1-3 distinct tags to every translation created by this run.
     *
     * The translations are streamed by their real ids (everything with
     * id > $afterId) rather than an assumed contiguous range, so it remains
     * correct even when the auto-increment counter has gaps.
     *
     * @param  array<int, int>  $tagIds
     */
    private function attachTags(int $afterId, array $tagIds, int $chunk): void
    {
        $query = DB::table('translations')->where('id', '>', $afterId);

        $bar = $this->output->createProgressBar($query->count());
        $bar->start();

        $tagPool = array_flip($tagIds);
        $maxTags = min(3, count($tagIds));
        $buffer = [];

        $flush = function () use (&$buffer): void {
            if ($buffer !== []) {
                DB::table('tag_translation')->insertOrIgnore($buffer);
                $buffer = [];
            }
        };

        $query->select('id')->orderBy('id')->lazy()->each(
            function (object $row) use (&$buffer, $tagPool, $maxTags, $chunk, $bar, $flush): void {
                foreach ((array) array_rand($tagPool, random_int(1, $maxTags)) as $tagId) {
                    $buffer[] = ['translation_id' => $row->id, 'tag_id' => (int) $tagId];
                }

                if (count($buffer) >= $chunk) {
                    $flush();
                }

                $bar->advance();
            },
        );

        $flush();
        $bar->finish();
        $this->newLine(2);
    }
}
