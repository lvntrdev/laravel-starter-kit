<?php

namespace Database\Seeders;

use App\Models\ContentLanguage;
use Illuminate\Database\Seeder;

class _04_ContentLanguageSeeder extends Seeder
{
    /**
     * Seed the default content languages.
     *
     * Content languages drive the locale tabs of translatable content fields
     * (TranslatableInput) — a separate concept from the admin UI locale, which
     * stays bound to lang files via config('app.languages').
     *
     * Idempotent: matched by `code` via updateOrCreate, so re-running install
     * (or invoking this seeder manually) never duplicates rows. Note the upgrade
     * flow (`sk:upgrade`) does not re-run domain seeders — existing apps get the
     * table via the auto-loaded migration and fall back to config('app.languages')
     * until this seeder is run, so re-seeding is a deliberate, safe step.
     */
    public function run(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'tr',
                'name' => 'Turkish',
                'native_name' => 'Türkçe',
                'direction' => 'ltr',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 1,
            ],
        ];

        foreach ($languages as $language) {
            ContentLanguage::updateOrCreate(
                ['code' => $language['code']],
                $language,
            );
        }

        $this->command?->info('Content languages seeded: '.count($languages).' rows.');
    }
}
