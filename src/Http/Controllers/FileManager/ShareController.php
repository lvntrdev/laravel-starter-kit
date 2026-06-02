<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Http\Controllers\FileManager;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Lvntr\StarterKit\Domain\FileManager\Actions\CreateShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\Actions\RevokeShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\DTOs\CreateShareLinkDTO;
use Lvntr\StarterKit\Domain\FileManager\Models\ShareRevocation;
use Lvntr\StarterKit\Http\Requests\FileManager\CreateShareLinkRequest;
use Lvntr\StarterKit\Http\Requests\FileManager\RevokeShareLinkRequest;
use Lvntr\StarterKit\Http\Resources\FileManager\ShareLinkResource;
use Lvntr\StarterKit\Http\Responses\ApiResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * FileManager — İmzalı Paylaşım Link Yönetimi.
 *
 * Endpoint'ler:
 *   GET  file-manager/share/{media}  — imzalı linki serve eder (public, signed)
 *   POST file-manager/share          — yeni share link üretir (auth gerekli)
 *   POST file-manager/share/revoke   — link'i geçersiz kılar (auth gerekli)
 *
 * TODO (multi-tenant): show() içinde tenant context'i middleware seviyesinde
 * set edilmeli. Şu an host uygulamanın global middleware'ine bırakılmıştır.
 * Multi-tenant ortamda signed URL, tenant subdomain/header ile birleştirilmeli
 * veya tenant_id signed URL parametresine eklenmeli.
 */
class ShareController extends Controller
{
    /**
     * İmzalı share link'i serve eder.
     *
     * Middleware zaten `signed` doğrulamasını yaptı — bu noktada
     * URL geçerli ve süresi dolmamış demektir.
     * Tek yapmamız gereken revocation lookup.
     *
     * @OA\Get(
     *   path="/file-manager/share/{media}",
     *   summary="Paylaşılan dosyayı indir",
     *   description="İmzalı geçici link ile dosyaya erişim. Revoke edilmişse 410 döner.",
     *   tags={"FileManager"},
     *
     *   @OA\Parameter(name="media", in="path", required=true, @OA\Schema(type="integer")),
     *
     *   @OA\Response(response=200, description="Dosya stream"),
     *   @OA\Response(response=403, description="Geçersiz veya süresi dolmuş imza"),
     *   @OA\Response(response=410, description="Link revoke edilmiş")
     * )
     */
    public function show(Media $media): StreamedResponse
    {
        // Signature parametresini URL'den çıkar ve hash'le
        $signature = (string) request()->query('signature', '');
        $tokenHash = hash('sha256', $signature);

        // Revocation kontrolü: composite (media_id + hash) ile 410 Gone.
        // Action composite ile yazıyor; lookup da aynı bileşikle yapılmalı.
        // Hash-only sorgulama, farklı medyanın revoke kaydını yanlış eşleştirebilir.
        $isRevoked = ShareRevocation::where('media_id', $media->id)
            ->where('signed_token_hash', $tokenHash)
            ->exists();

        if ($isRevoked) {
            abort(410, __('sk-file-manager.share.revoked'));
        }

        // DDD sınırı: Storage çağrısı FileManager domain'inden yapılmalı,
        // ancak show() public endpoint olduğu için doğrudan stream yapılıyor.
        // İleride DownloadFileAction benzeri bir action'a taşınabilir.
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        return $disk->download($path, $media->file_name, [
            'Content-Type' => $media->mime_type,
        ]);
    }

    /**
     * Yeni share link üretir.
     *
     * @OA\Post(
     *   path="/file-manager/share",
     *   summary="Share link oluştur",
     *   security={{"bearerAuth": {}}},
     *   tags={"FileManager"},
     *
     *   @OA\RequestBody(
     *     required=true,
     *
     *     @OA\JsonContent(
     *       required={"media_id"},
     *
     *       @OA\Property(property="media_id", type="integer"),
     *       @OA\Property(property="expires_in_hours", type="integer", minimum=1, maximum=720)
     *     )
     *   ),
     *
     *   @OA\Response(response=201, description="Link oluşturuldu",
     *
     *     @OA\JsonContent(ref="#/components/schemas/ShareLinkResource")
     *   ),
     *
     *   @OA\Response(response=403, description="Yetki yok"),
     *   @OA\Response(response=422, description="Validation hatası")
     * )
     */
    public function store(
        CreateShareLinkRequest $request,
        CreateShareLinkAction $action,
    ): ApiResponse|JsonResponse {
        // Media kaydını yükle
        /** @var Media $media */
        $media = Media::findOrFail($request->input('media_id'));

        // K4: Gate::policy yerine flat gate kullanılıyor (share-media).
        // O3: Defense-in-depth — Request::authorize() zaten kontrol etti,
        // controller'da ikinci kez kontrol edilir.
        Gate::authorize('share-media', $media);

        $user = $request->user();

        $dto = CreateShareLinkDTO::fromArray([
            'media' => $media,
            'owner_type' => $media->model_type,
            'owner_id' => (string) $media->model_id,
            'expires_in_hours' => $request->input('expires_in_hours'),
        ]);

        $result = $action->execute($dto);

        // K3: to_api() envelope — useApi composable {success, data, ...} bekliyor.
        return to_api(new ShareLinkResource($result->toArray()), status: 201);
    }

    /**
     * Share link'i revoke eder.
     *
     * @OA\Post(
     *   path="/file-manager/share/revoke",
     *   summary="Share link'i geçersiz kıl",
     *   security={{"bearerAuth": {}}},
     *   tags={"FileManager"},
     *
     *   @OA\RequestBody(
     *     required=true,
     *
     *     @OA\JsonContent(
     *       required={"media_id","token"},
     *
     *       @OA\Property(property="media_id", type="integer"),
     *       @OA\Property(property="token", type="string", minLength=64, maxLength=64)
     *     )
     *   ),
     *
     *   @OA\Response(response=200, description="Revoke edildi"),
     *   @OA\Response(response=403, description="Yetki yok"),
     *   @OA\Response(response=422, description="Validation hatası")
     * )
     */
    public function revoke(
        RevokeShareLinkRequest $request,
        RevokeShareLinkAction $action,
    ): ApiResponse|JsonResponse {
        /** @var Media $media */
        $media = Media::findOrFail($request->input('media_id'));

        // K4: Gate::policy yerine flat gate kullanılıyor (revoke-share-media).
        // K2 / O3: RevokeShareLinkRequest::authorize() ownership'i zaten doğruladı;
        // controller'da ikinci kez kontrol defense-in-depth sağlar.
        Gate::authorize('revoke-share-media', $media);

        $user = $request->user();

        $action->execute(
            media: $media,
            tokenHash: $request->string('token')->toString(),
            // users.id is a UUID string — never cast to int (that would truncate
            // the identifier to a meaningless number and break the users FK).
            revokedByUserId: $user ? (string) $user->getAuthIdentifier() : null,
        );

        // K3: to_api() envelope — useApi composable {success, data, ...} bekliyor.
        return to_api(['revoked' => true, 'media_id' => $media->getKey()]);
    }
}
