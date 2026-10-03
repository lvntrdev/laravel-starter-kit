<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Issued signed share link record.
 *
 * Every generated share URL is recorded here by the SHA256 hash of its
 * `signature` parameter (never the URL or the raw signature), so active
 * links can be listed and revoked per media.
 *
 * @property int $id
 * @property int $media_id
 * @property string $signed_token_hash
 * @property Carbon $expires_at
 * @property string|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShareLink extends Model
{
    protected $table = 'file_manager_share_links';

    protected $fillable = [
        'media_id',
        'signed_token_hash',
        'expires_at',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * The shared media record.
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Links of the given media that are neither expired nor revoked.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveFor(Builder $query, Media $media): Builder
    {
        $links = $this->getTable();
        $revocations = (new ShareRevocation)->getTable();

        return $query
            ->where($links.'.media_id', $media->getKey())
            ->where($links.'.expires_at', '>', now())
            ->whereNotExists(function (QueryBuilder $sub) use ($links, $revocations): void {
                $sub->selectRaw('1')
                    ->from($revocations)
                    ->whereColumn($revocations.'.media_id', $links.'.media_id')
                    ->whereColumn($revocations.'.signed_token_hash', $links.'.signed_token_hash');
            });
    }
}
