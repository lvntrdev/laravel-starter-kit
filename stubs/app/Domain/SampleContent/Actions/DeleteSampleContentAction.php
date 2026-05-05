<?php

namespace App\Domain\SampleContent\Actions;

use App\Domain\SampleContent\Events\SampleContentDeleted;
use Lvntr\StarterKit\Domain\Shared\Actions\BaseAction;
use App\Models\SampleContent;

/**
 * Action: Delete a sample_content.
 */
class DeleteSampleContentAction extends BaseAction
{
    public function execute(SampleContent $sample_content, int|string|null $performedBy = null): bool
    {
        $sample_contentId = $sample_content->id;

        $result = (bool) $sample_content->delete();

        if ($result) {
            SampleContentDeleted::dispatch($sample_contentId, $performedBy);
        }

        return $result;
    }
}
