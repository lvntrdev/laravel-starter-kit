<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Http\Resources\FileManager;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Share link oluşturma response'u.
 *
 * @property string $url Tam imzalı URL
 * @property string $expires_at ISO 8601 bitiş zamanı
 * @property string $token_hash SHA256 token hash'i (revoke için gerekli)
 *
 * @OA\Schema(
 *   schema="ShareLinkResource",
 *   type="object",
 *   required={"url","expires_at","token_hash"},
 *
 *   @OA\Property(property="url", type="string", format="uri", description="İmzalı geçici paylaşım URL'si"),
 *   @OA\Property(property="expires_at", type="string", format="date-time", description="Linkin geçerlilik sona erme zamanı (ISO 8601)"),
 *   @OA\Property(property="token_hash", type="string", minLength=64, maxLength=64, description="Signature parametresinin SHA256 hash'i; revoke isteğinde kullanılır")
 * )
 */
class ShareLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource['url'],
            'expires_at' => $this->resource['expires_at'],
            'token_hash' => $this->resource['token_hash'],
        ];
    }
}
