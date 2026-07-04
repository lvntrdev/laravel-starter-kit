<?php

namespace Lvntr\StarterKit\Domain\ContentLanguage\Actions;

use App\Models\ContentLanguage;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Exceptions\ApiException;

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

        $deleted = (bool) $contentLanguage->delete();

        // Audit sink (Task 8): see CreateContentLanguageAction. The model's
        // attributes are still in memory after delete(), so the subject
        // association + code/name are recorded even though the row is gone.
        if ($deleted && auth()->check()) {
            activity('audit')
                ->performedOn($contentLanguage)
                ->event('deleted')
                ->withProperties(['code' => $contentLanguage->code, 'name' => $contentLanguage->name])
                ->log('Content language deleted');
        }

        return $deleted;
    }
}
