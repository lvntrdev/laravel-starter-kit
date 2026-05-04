<?php

namespace App\Domain\SampleContent\Events;

use App\Models\SampleContent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SampleContentUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SampleContent $sample_content,
        /** @var array<string> */
        public readonly array $changedFields = [],
        public readonly int|string|null $performedBy = null,
    ) {}
}
