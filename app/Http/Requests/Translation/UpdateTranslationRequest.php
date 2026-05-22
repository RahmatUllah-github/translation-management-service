<?php

declare(strict_types=1);

namespace App\Http\Requests\Translation;

use App\Models\Locale;
use App\Models\Translation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a partial translation update. Every field is optional, but any
 * field that is present must be non-empty (`sometimes` + `required`).
 */
final class UpdateTranslationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('locale')) {
            $this->merge([
                'locale_id' => Locale::query()
                    ->where('code', trim((string) $this->input('locale')))
                    ->value('id'),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Translation $translation */
        $translation = $this->route('translation');

        // Uniqueness is checked against the effective locale: the new one if
        // supplied, otherwise the translation's current locale.
        $localeId = $this->input('locale_id') ?? $translation->locale_id;

        return [
            'locale' => ['sometimes', 'string', Rule::exists('locales', 'code')],
            'locale_id' => ['nullable', 'integer'],
            'key' => [
                'sometimes', 'required', 'string', 'max:191',
                Rule::unique('translations', 'key')
                    ->where('locale_id', $localeId)
                    ->ignore($translation->id),
            ],
            'content' => ['sometimes', 'required', 'string', 'max:65535'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:64', 'distinct'],
        ];
    }
}
