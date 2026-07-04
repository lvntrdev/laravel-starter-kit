<?php

namespace Lvntr\StarterKit\Domain\ContentLanguage\Actions;

use App\Models\ContentLanguage;
use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\ContentLanguage\DTOs\ContentLanguageDTO;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use Lvntr\StarterKit\Exceptions\ApiException;

/**
 * Action: Update an existing content language.
 *
 * Enforces the same single-default invariant as the create action and adds
 * the "last default" guard: the only active default may not be demoted
 * (is_default → false) or deactivated (is_active → false), because that would
 * leave the translatable-field fallback chain with no default to resolve to.
 *
 * The attribute update + sibling demotion run inside a transaction so a
 * partial write can never leave zero or multiple defaults behind.
 */
class UpdateContentLanguageAction extends BaseAction
{
    /**
     * @throws ApiException When the change would remove the last active default.
     */
    public function execute(ContentLanguage $model, ContentLanguageDTO $dto): ContentLanguage
    {
        $model = DB::transaction(function () use ($model, $dto): ContentLanguage {
            $attributes = $dto->toArray();

            $this->guardLastDefault($model, $attributes);

            // A default language must remain active.
            if ($attributes['is_default']) {
                $attributes['is_active'] = true;
            }

            $model->update($attributes);

            if ($model->is_default) {
                $this->demoteOtherDefaults($model->id);
            }

            $model->refresh();

            return $model;
        });

        // Audit sink (Task 8): see CreateContentLanguageAction.
        if (auth()->check()) {
            activity('audit')
                ->performedOn($model)
                ->event('updated')
                ->withProperties(['code' => $model->code, 'name' => $model->name])
                ->log('Content language updated');
        }

        return $model;
    }

    /**
     * Block changes that would strip the system of its only active default.
     *
     * If this row is the current default, the incoming attributes may not turn
     * off is_default or is_active. Promoting a different row to default is the
     * supported way to move the default elsewhere (it demotes this one safely).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ApiException
     */
    private function guardLastDefault(ContentLanguage $model, array $attributes): void
    {
        if (! $model->is_default) {
            return;
        }

        $losingDefault = $attributes['is_default'] === false;
        $beingDeactivated = $attributes['is_active'] === false;

        if ($losingDefault || $beingDeactivated) {
            throw ApiException::unprocessable(
                __('sk-content-languages.errors.last_default'),
            );
        }
    }

    /**
     * Demote every other row's is_default flag to false.
     */
    private function demoteOtherDefaults(int $exceptId): void
    {
        ContentLanguage::query()
            ->where('id', '!=', $exceptId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
