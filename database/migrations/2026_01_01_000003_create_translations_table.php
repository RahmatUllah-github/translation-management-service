<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Core table. Indexing strategy (tuned for 100k+ rows):
     *
     *  - UNIQUE(locale_id, key): enforces one value per key per locale AND is
     *    the workhorse index — it serves the export query
     *    (WHERE locale_id = ? ORDER BY key) as an index-ordered scan and any
     *    locale+key filtering. The FK on locale_id reuses its leading column,
     *    so no separate locale_id index is needed.
     *  - INDEX(key): standalone, for cross-locale key search and prefix
     *    matches (LIKE 'home%') when no locale is supplied.
     *  - FULLTEXT(content): MySQL 8 InnoDB full-text index so content search
     *    uses MATCH ... AGAINST instead of a leading-wildcard LIKE (which is a
     *    full table scan at scale).
     *
     * `key` is varchar(191) to keep the utf8mb4 index compact and cacheable.
     */
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('locale_id')->constrained()->cascadeOnDelete();
            $table->string('key', 191);
            $table->text('content');
            $table->timestamps();

            $table->unique(['locale_id', 'key']);
            $table->index('key');
            $table->fullText('content');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
