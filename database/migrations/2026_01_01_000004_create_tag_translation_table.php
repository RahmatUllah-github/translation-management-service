<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Pivot for the many-to-many translation <-> tag relation.
     *
     *  - PRIMARY(translation_id, tag_id): de-duplicates attachments and serves
     *    "tags of a translation" lookups; the FK on translation_id reuses it.
     *  - INDEX(tag_id, translation_id): serves the reverse lookup
     *    "translations carrying tag X" — the tag filter — and covers the
     *    tag_id FK.
     */
    public function up(): void
    {
        Schema::create('tag_translation', function (Blueprint $table): void {
            $table->foreignId('translation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->primary(['translation_id', 'tag_id']);
            $table->index(['tag_id', 'translation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_translation');
    }
};
