<?php

namespace App\Domain\SampleContent\Listeners;

use App\Domain\SampleContent\Events\SampleContentDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogSampleContentDeleted implements ShouldQueue
{
    public function handle(SampleContentDeleted $event): void
    {
        Log::channel('stack')->info('SampleContent Deleted', [
            'sample_content_id' => $event->sample_contentId,
            'deleted_by' => $event->performedBy,
        ]);
    }
}
