<?php

namespace Lvntr\StarterKit\Domain\ContentLanguage\Actions;

use App\Models\ContentLanguage;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\ContentLanguage\DTOs\ContentLanguageDTO;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Create a new content language.
 *
 * Single-default invariant: at most one content language may carry
 * is_default = true. When this row is created as the default, every other
 * row is demoted in the same transaction so the create + demotion can never
 * leave two defaults behind.
 *
 * A default must always be active — creating an inactive default is coerced
 * to active so the translatable-field fallback chain never points at a
 * disabled language.
 */
class CreateContentLanguageAction extends BaseAction
{
    public function execute(ContentLanguageDTO $dto): ContentLanguage
    {
        return DB::transaction(function () use ($dto): ContentLanguage {
            $attributes = $dto->toArray();

            // A default language must remain active so the content fallback
            // always resolves to an enabled language.
            if ($attributes['is_default']) {
                $attributes['is_active'] = true;
            }

            $language = ContentLanguage::create($attributes);

            if ($language->is_default) {
                $this->demoteOtherDefaults($language->id);
            }

            return $language;
        });
    }

    /**
     * Demote every other row's is_default flag to false, leaving the just-set
     * default as the sole default.
     */
    private function demoteOtherDefaults(int $exceptId): void
    {
        ContentLanguage::query()
            ->where('id', '!=', $exceptId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
