<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Locales are stored as a first-class table — not an enum or free string —
     * so new languages can be added at runtime without a migration, while a
     * UNIQUE code and FK integrity prevent typos and orphaned translations.
     */
    public function up(): void
    {
        Schema::create('locales', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 10)->unique();   // e.g. en, fr, es, pt-BR
            $table->string('name', 64);             // e.g. "English"
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locales');
    }
};
