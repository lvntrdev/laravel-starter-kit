<?php

namespace Database\Seeders;

use App\Models\Definition;
use Illuminate\Database\Seeder;

class _02_DefinitionSeeder extends Seeder
{
    /**
     * Seed the definitions table using upsert.
     *
     * - Existing rows are updated (ID preserved).
     * - New rows are inserted.
     * - Rows removed from the array are soft-deleted.
     *
     * Format: 'key' => [ [value, label, order, lang, severity?, icon?, explanation?], ... ]
     */
    public function run(): void
    {
        $definitions = $this->definitions();

        $rows = [];
        $now = now();

        foreach ($definitions as $key => $items) {
            foreach ($items as $item) {
                $rows[] = [
                    'key' => $key,
                    'value' => $item[0],
                    'label' => $item[1],
                    'order' => $item[2] ?? 0,
                    'lang' => $item[3] ?? 'en',
                    'severity' => $item[4] ?? null,
                    'icon' => $item[5] ?? null,
                    'explanation' => $item[6] ?? null,
                    'is_active' => true,
                    'visibility' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Restore any previously soft-deleted rows that are back in the array
        Definition::onlyTrashed()
            ->whereIn('key', array_keys($definitions))
            ->restore();

        // Upsert: insert new rows, update existing ones (matched by key+value+lang)
        foreach (array_chunk($rows, 100) as $chunk) {
            Definition::upsert(
                $chunk,
                ['key', 'value', 'lang'],
                ['label', 'order', 'severity', 'icon', 'explanation', 'is_active', 'visibility', 'updated_at'],
            );
        }

        // Soft-delete rows that are no longer in the definitions array
        $this->cleanRemovedRows($definitions);

        $this->command?->info('Definitions seeded: '.count($rows).' rows.');
    }

    /**
     * Soft-delete definition rows that were removed from the array.
     *
     * @param  array<string, list<array>>  $definitions
     */
    private function cleanRemovedRows(array $definitions): void
    {
        $keys = array_keys($definitions);

        // Soft-delete entire keys that no longer exist
        Definition::whereNotIn('key', $keys)->delete();

        // Soft-delete specific values removed within existing keys
        foreach ($definitions as $key => $items) {
            $values = array_map(fn (array $item) => $item[0], $items);

            Definition::where('key', $key)
                ->whereNotIn('value', $values)
                ->delete();
        }
    }

    /**
     * Define all DB-based definitions here.
     *
     * Format per item: [value, label, order, lang?, severity?, icon?, explanation?]
     *
     * @return array<string, list<array>>
     */
    private function definitions(): array
    {
        return [

            // ── Example definitions ───────────────────────────────────────

            // 'identityType' => [
            //     // [value, label, order, lang, severity]
            //     [1, 'Türkiye', 0, 'tr', 'contrast'],
            //     [2, 'Yabancı Uyruklu', 1, 'tr', 'green,soft'],
            // ],

            'gender' => [
                ['female', 'Kadın', 0, 'tr'],
                ['female', 'Female', 0, 'en'],
                ['male', 'Erkek', 1, 'tr'],
                ['male', 'Male', 1, 'en'],
            ],

        ];
    }
}
