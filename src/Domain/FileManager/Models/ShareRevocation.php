<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Domain\FileManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Revoke edilmiş signed share link kaydı.
 *
 * Bir URL share edilip sonradan geçersiz kılındığında bu tabloya
 * kayıt eklenir. Signed URL'nin `signature` parametresinin SHA256
 * hash'i `signed_token_hash` alanında tutulur. Serve sırasında
 * (ShareController@show) bu hash lookup yapılarak 410 Gone dönülür.
 *
 * @property int $id
 * @property string|null $tenant_id
 * @property int $media_id
 * @property string $signed_token_hash
 * @property Carbon $revoked_at
 * @property int|null $revoked_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ShareRevocation extends Model
{
    protected $table = 'file_manager_share_revocations';

    protected $fillable = [
        'tenant_id',
        'media_id',
        'signed_token_hash',
        'revoked_at',
        'revoked_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Revoke edilen medya kaydı.
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
