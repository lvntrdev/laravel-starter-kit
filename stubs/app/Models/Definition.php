<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;

class Definition extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'value',
        'label',
        'explanation',
        'severity',
        'icon',
        'is_active',
        'order',
        'visibility',
        'lang',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'visibility' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Flush the definition cache whenever a row changes so definition CRUD
     * reflects immediately instead of after the ~1h TTL (mirrors
     * ContentLanguage::booted()). Covers every Eloquent write path:
     * create/update, soft delete, restore and force delete. Bulk upsert
     * (the DefinitionSeeder) bypasses model events, so the seeder still
     * flushes manually after its upsert.
     */
    protected static function booted(): void
    {
        $flush = static fn () => app(DefinitionService::class)->clearCache();

        static::saved($flush);
        static::deleted($flush);
        static::restored($flush);
        static::forceDeleted($flush);
    }
}
