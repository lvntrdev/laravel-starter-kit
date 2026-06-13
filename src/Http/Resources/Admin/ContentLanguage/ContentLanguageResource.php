<?php

namespace Lvntr\StarterKit\Http\Resources\Admin\ContentLanguage;

use App\Models\ContentLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentLanguage
 */
class ContentLanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'direction' => $this->direction,
            'flag' => $this->flag,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'fallback_code' => $this->fallback_code,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
