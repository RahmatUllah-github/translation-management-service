<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\Tag;
use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * Seeds a small, realistic demo set — every locale gets a real, human
 * translation of every key — so the API is usable immediately after
 * `migrate --seed` and the export endpoint returns actual localized content.
 * Large-volume data is handled separately by `translations:seed`.
 */
final class TranslationSeeder extends Seeder
{
    /**
     * Per-locale translations, keyed by locale code then translation key.
     * Adding a new locale only requires a new entry here.
     *
     * @return array<string, array<string, string>>
     */
    private function translations(): array
    {
        return [
            'en' => [
                'homepage.title' => 'Welcome',
                'homepage.subtitle' => 'Manage your translations with ease',
                'auth.login' => 'Log in',
                'auth.logout' => 'Log out',
                'auth.register' => 'Create an account',
                'nav.dashboard' => 'Dashboard',
                'nav.settings' => 'Settings',
                'errors.not_found' => 'The requested page could not be found',
            ],
            'fr' => [
                'homepage.title' => 'Bienvenue',
                'homepage.subtitle' => 'Gérez vos traductions en toute simplicité',
                'auth.login' => 'Se connecter',
                'auth.logout' => 'Se déconnecter',
                'auth.register' => 'Créer un compte',
                'nav.dashboard' => 'Tableau de bord',
                'nav.settings' => 'Paramètres',
                'errors.not_found' => 'La page demandée est introuvable',
            ],
            'es' => [
                'homepage.title' => 'Bienvenido',
                'homepage.subtitle' => 'Gestiona tus traducciones con facilidad',
                'auth.login' => 'Iniciar sesión',
                'auth.logout' => 'Cerrar sesión',
                'auth.register' => 'Crear una cuenta',
                'nav.dashboard' => 'Panel de control',
                'nav.settings' => 'Configuración',
                'errors.not_found' => 'No se ha encontrado la página solicitada',
            ],
            'de' => [
                'homepage.title' => 'Willkommen',
                'homepage.subtitle' => 'Verwalten Sie Ihre Übersetzungen mit Leichtigkeit',
                'auth.login' => 'Anmelden',
                'auth.logout' => 'Abmelden',
                'auth.register' => 'Konto erstellen',
                'nav.dashboard' => 'Übersicht',
                'nav.settings' => 'Einstellungen',
                'errors.not_found' => 'Die angeforderte Seite wurde nicht gefunden',
            ],
            'ar' => [
                'homepage.title' => 'مرحبًا',
                'homepage.subtitle' => 'أدر ترجماتك بكل سهولة',
                'auth.login' => 'تسجيل الدخول',
                'auth.logout' => 'تسجيل الخروج',
                'auth.register' => 'إنشاء حساب',
                'nav.dashboard' => 'لوحة التحكم',
                'nav.settings' => 'الإعدادات',
                'errors.not_found' => 'الصفحة المطلوبة غير موجودة',
            ],
            'ur' => [
                'homepage.title' => 'خوش آمدید',
                'homepage.subtitle' => 'اپنے تراجم آسانی سے منظم کریں',
                'auth.login' => 'لاگ ان کریں',
                'auth.logout' => 'لاگ آؤٹ کریں',
                'auth.register' => 'اکاؤنٹ بنائیں',
                'nav.dashboard' => 'ڈیش بورڈ',
                'nav.settings' => 'ترتیبات',
                'errors.not_found' => 'درخواست کردہ صفحہ نہیں ملا',
            ],
            'ps' => [
                'homepage.title' => 'ښه راغلاست',
                'homepage.subtitle' => 'خپلې ژباړې په اسانۍ سره اداره کړئ',
                'auth.login' => 'ننوتل',
                'auth.logout' => 'وتل',
                'auth.register' => 'حساب جوړ کړئ',
                'nav.dashboard' => 'ډېشبورډ',
                'nav.settings' => 'تنظیمات',
                'errors.not_found' => 'غوښتل شوې پاڼه و نه موندل شوه',
            ],
        ];
    }

    public function run(): void
    {
        $locales = Locale::query()->get()->keyBy('code');
        $tagIds = Tag::query()->pluck('id');

        foreach ($this->translations() as $code => $keyMap) {
            $locale = $locales->get($code);

            // Skip silently if the LocaleSeeder hasn't seeded this locale yet —
            // keeps the seeder idempotent and tolerant of partial setups.
            if ($locale === null) {
                continue;
            }

            foreach ($keyMap as $key => $content) {
                $translation = Translation::query()->updateOrCreate(
                    ['locale_id' => $locale->id, 'key' => $key],
                    ['content' => $content],
                );

                $translation->tags()->sync(
                    $tagIds->random(min(2, $tagIds->count()))->all(),
                );
            }
        }
    }
}
