<?php

namespace App\Domain\ContentLanguage\Actions;

use App\Exceptions\ApiException;
use App\Models\ContentLanguage;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;

/**
 * Action: Delete a content language.
 *
 * Guard: the default content language may not be deleted — at least one
 * active default must always remain so translatable-field tabs and the
 * content fallback chain keep a language to resolve to. Promote another
 * language to default first, then delete this one.
 */
class DeleteContentLanguageAction extends BaseAction
{
    /**
     * @throws ApiException When attempting to delete the default language.
     */
    public function execute(ContentLanguage $contentLanguage): bool
    {
        if ($contentLanguage->is_default) {
            throw ApiException::unprocessable(
                __('sk-content-languages.errors.delete_default'),
            );
        }

        return (bool) $contentLanguage->delete();
    }
}
