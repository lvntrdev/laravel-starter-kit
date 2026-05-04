<?php

namespace App\Http\Resources\Admin\SampleContent;

use App\Models\SampleContent;
use App\Support\TranslatableQueryHelpers;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SampleContent
 */
class SampleContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => TranslatableQueryHelpers::resourceShape($this->resource, 'title'),
            'description' => TranslatableQueryHelpers::resourceShape($this->resource, 'description'),
            'content' => TranslatableQueryHelpers::resourceShape($this->resource, 'content'),
            'user' => [
                'id' => $this->user_id,
                'name' => $this->whenLoaded('user', fn () => $this->resource->user->full_name),
            ],
            'created_at' => format_date($this->created_at),
            'updated_at' => format_date($this->updated_at),
        ];
    }
}
