<?php

/*
|--------------------------------------------------------------------------
| FileManager — issued share link registry + active-link list (red line)
|--------------------------------------------------------------------------
|
| Locks the security contract of `file_manager_share_links` and of the
| `file-manager.share.index` endpoint: who may list a media's live links,
| what one item carries, and what never leaves the server (the signed URL
| and its raw signature).
|
| Only wiring the package suite cannot autoload is mirrored here:
|   - the two Gate::before hooks a consumer app runs — the kit's system_admin
|     bypass (configureGates() needs App\Models\User) and Spatie's
|     permission check — so every decision still goes through the real Gate;
|   - App\Models\GlobalFileBucket (ShareListGlobalBucket below), so the REAL
|     built-in `global` context closure decides files.read vs files.update.
|
*/

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable as AuthorizableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Lvntr\StarterKit\Domain\FileManager\Actions\CreateShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\DTOs\CreateShareLinkDTO;
use Lvntr\StarterKit\Domain\FileManager\DTOs\ShareLinkResultDTO;
use Lvntr\StarterKit\Domain\FileManager\Models\ShareLink;
use Lvntr\StarterKit\Tests\Stubs\TestMedia;

// ──────────────────────────────────────────────────────────────────────────────
// Fixtures
// ──────────────────────────────────────────────────────────────────────────────

/** Stand-in for App\Models\GlobalFileBucket, which the package suite cannot autoload. */
final class ShareListGlobalBucket extends Model
{
    protected $table = 'global_file_buckets';

    public $incrementing = false;

    protected $keyType = 'string';

    public static function singleton(): self
    {
        return (new self)->forceFill(['id' => 'global-bucket']);
    }
}

/** Same row shape as ShareLinkTest's insertShareTestMedia(), plus a trashed flag. */
function shareListMedia(string $modelType = 'user', string $modelId = '1', bool $trashed = false): TestMedia
{
    $id = DB::table('media')->insertGetId([
        'model_type' => $modelType,
        'model_id' => $modelId,
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => 'share-list-'.Str::random(6),
        'file_name' => 'document.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'conversions_disk' => null,
        'size' => 1024,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => null,
        'folder_id' => null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => $trashed ? now() : null,
    ]);

    return TestMedia::withTrashed()->find($id);
}

/**
 * Persisted `users` row (created_by / revoked_by FKs) + an Eloquent,
 * Authorizable actor. Permissions and roles are answered only by the
 * Gate::before hooks registered in beforeEach().
 *
 * @param  list<string>  $permissions
 * @param  list<string>  $roles
 */
function shareListActor(int $id, array $permissions = [], array $roles = []): Authenticatable
{
    DB::table('users')->insertOrIgnore([
        'id' => $id,
        'name' => "share-list-{$id}",
        'email' => "share-list-{$id}@example.test",
        'password' => Str::random(40),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $actor = new class extends Model implements Authenticatable, AuthorizableContract
    {
        use AuthenticatableTrait, AuthorizableTrait;

        /** @var list<string> */
        public array $grantedPermissions = [];

        /** @var list<string> */
        public array $grantedRoles = [];

        protected $table = 'users';

        public $timestamps = false;

        public function getMorphClass(): string
        {
            return 'user';
        }
    };

    $actor->forceFill(['id' => $id]);
    $actor->grantedPermissions = $permissions;
    $actor->grantedRoles = $roles;

    return $actor;
}

function shareListIssue(TestMedia $media, int $hours = 24): ShareLinkResultDTO
{
    return app(CreateShareLinkAction::class)->execute(CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => $media->model_type,
        'owner_id' => (string) $media->model_id,
        'expires_in_hours' => $hours,
    ]));
}

function shareListIndex(?Authenticatable $actor, mixed $mediaId): TestResponse
{
    if ($actor !== null) {
        test()->actingAs($actor);
    }

    return test()->getJson(route('file-manager.share.index', $mediaId === null ? [] : ['media_id' => $mediaId]));
}

function shareListSignature(string $url): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return (string) ($query['signature'] ?? '');
}

beforeEach(function (): void {
    config([
        // Authenticated create/revoke write an `audit` activity row; this suite
        // has no activity_log table (ShareLinkAuditTest covers that sink).
        'activitylog.enabled' => false,
        'file-manager.models.global_bucket' => ShareListGlobalBucket::class,
        'file-manager.share.enabled' => true,
        'file-manager.share.allow_revoke' => true,
    ]);

    // Mirrors StarterKitServiceProvider::configureGates() (system_admin bypass).
    Gate::before(fn ($user): ?bool => in_array('system_admin', $user->grantedRoles ?? [], true) ? true : null);
    // Mirrors Spatie's PermissionRegistrar hook (permission name → ability).
    Gate::before(fn ($user, string $ability): ?bool => in_array($ability, $user->grantedPermissions ?? [], true) ? true : null);
});

// ──────────────────────────────────────────────────────────────────────────────
// A) Persistence — one hashed row per issued link
// ──────────────────────────────────────────────────────────────────────────────

it('stores exactly one row per created link: signature hash, expiry and creator only', function (): void {
    $this->freezeTime();
    $owner = shareListActor(1);
    $media = shareListMedia('user', '1');

    $response = $this->actingAs($owner)
        ->postJson(route('file-manager.share.store'), ['media_id' => $media->getKey()])
        ->assertCreated();

    $signature = shareListSignature((string) $response->json('data.url'));
    $row = ShareLink::sole();

    expect($signature)->toHaveLength(64)
        ->and($row->media_id)->toBe($media->getKey())
        ->and($row->signed_token_hash)->toBe(hash('sha256', $signature))
        ->and($row->signed_token_hash)->toBe($response->json('data.token_hash'))
        ->and(to_api_date($row->expires_at))->toBe($response->json('data.expires_at'))
        ->and($row->created_by_user_id)->toBe('1')
        ->and(json_encode($row->getAttributes()))->not->toContain($signature);
});

it('answers two identical creates in the same second with 201 and keeps a single row', function (): void {
    $this->freezeTime();
    $owner = shareListActor(1);
    $media = shareListMedia('user', '1');

    $first = $this->actingAs($owner)
        ->postJson(route('file-manager.share.store'), ['media_id' => $media->getKey()])
        ->assertCreated();
    $second = $this->actingAs($owner)
        ->postJson(route('file-manager.share.store'), ['media_id' => $media->getKey()])
        ->assertCreated();

    expect($second->json('data.token_hash'))->toBe($first->json('data.token_hash'))
        ->and(ShareLink::count())->toBe(1);
});

it('has no column that could hold the URL or the raw signature', function (): void {
    expect(Schema::getColumnListing('file_manager_share_links'))->toEqualCanonicalizing([
        'id', 'media_id', 'signed_token_hash', 'expires_at', 'created_by_user_id', 'created_at', 'updated_at',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// B) Filtering and item shape
// ──────────────────────────────────────────────────────────────────────────────

it('lists only live links of the requested media, newest first, as hash + dates', function (): void {
    $owner = shareListActor(1);
    $media = shareListMedia('user', '1');
    $otherMedia = shareListMedia('user', '1');

    $expired = shareListIssue($media, hours: 1);
    $this->travel(1)->minutes();
    $revoked = shareListIssue($media);
    $this->travel(1)->minutes();
    $older = shareListIssue($media);
    $this->travel(1)->minutes();
    $newer = shareListIssue($media);
    $foreign = shareListIssue($otherMedia);

    $this->actingAs($owner)
        ->postJson(route('file-manager.share.revoke'), ['media_id' => $media->getKey(), 'token' => $revoked->tokenHash])
        ->assertOk();

    // Past the 1h link's expiry, well inside the 24h links'.
    $this->travel(2)->hours();

    $response = shareListIndex($owner, $media->getKey())->assertOk();
    $items = $response->json('data');

    expect(array_column($items, 'token_hash'))->toBe([$newer->tokenHash, $older->tokenHash])
        ->and($items[0]['expires_at'])->toBe(to_api_date($newer->expiresAt));

    foreach ($items as $item) {
        expect(array_keys($item))->toBe(['token_hash', 'expires_at', 'created_at']);
    }

    $body = (string) $response->getContent();
    expect($body)->not->toContain('signature=');

    foreach ([$expired, $revoked, $older, $newer, $foreign] as $link) {
        expect($body)->not->toContain(shareListSignature($link->url))
            ->not->toContain($link->url)
            ->not->toContain(trim((string) json_encode($link->url), '"'));
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// C) Authorization matrix — GET file-manager.share.index
// ──────────────────────────────────────────────────────────────────────────────

it('answers 401 to an unauthenticated caller', function (): void {
    $media = shareListMedia('user', '1');
    shareListIssue($media);

    shareListIndex(null, $media->getKey())->assertUnauthorized();
});

it('denies a stranger who neither owns the file nor manages its context', function (): void {
    $media = shareListMedia('user', '1');
    $link = shareListIssue($media);

    $response = shareListIndex(shareListActor(2), $media->getKey())->assertForbidden();

    expect((string) $response->getContent())->not->toContain($link->tokenHash);
});

it('denies files.read alone on a global-bucket file', function (): void {
    $media = shareListMedia(ShareListGlobalBucket::class, 'global-bucket');
    shareListIssue($media);

    shareListIndex(shareListActor(3, ['files.read']), $media->getKey())->assertForbidden();
});

it('answers 403, never 404 or 422, for a missing, non-numeric or unknown media_id', function (mixed $mediaId): void {
    shareListIssue(shareListMedia('user', '1'));

    shareListIndex(shareListActor(1), $mediaId)->assertForbidden();
})->with([
    'missing' => [null],
    'non-numeric' => ['abc'],
    'unknown' => [987654],
]);

it('denies the owner when allow_revoke is off', function (): void {
    $media = shareListMedia('user', '1');
    shareListIssue($media);
    config(['file-manager.share.allow_revoke' => false]);

    shareListIndex(shareListActor(1), $media->getKey())->assertForbidden();
});

it('denies the owner when share is disabled at runtime (route stays mounted, policy re-checks)', function (): void {
    $media = shareListMedia('user', '1');
    shareListIssue($media);
    config(['file-manager.share.enabled' => false]);

    shareListIndex(shareListActor(1), $media->getKey())->assertForbidden();
});

it('lets the owner list', function (): void {
    $media = shareListMedia('user', '1');
    $link = shareListIssue($media);

    shareListIndex(shareListActor(1), $media->getKey())
        ->assertOk()
        ->assertJsonPath('data.0.token_hash', $link->tokenHash);
});

it('lets a files.update holder list on a global-bucket file', function (): void {
    $media = shareListMedia(ShareListGlobalBucket::class, 'global-bucket');
    $link = shareListIssue($media);

    shareListIndex(shareListActor(4, ['files.update']), $media->getKey())
        ->assertOk()
        ->assertJsonPath('data.0.token_hash', $link->tokenHash);
});

it('lets system_admin list a file it does not own', function (): void {
    $media = shareListMedia('user', '1');
    $link = shareListIssue($media);

    shareListIndex(shareListActor(5, roles: ['system_admin']), $media->getKey())
        ->assertOk()
        ->assertJsonPath('data.0.token_hash', $link->tokenHash);
});

it('still lists the links of a trashed media for an authorized user', function (): void {
    $media = shareListMedia('user', '1');
    $link = shareListIssue($media);
    $media->delete();

    expect(TestMedia::onlyTrashed()->whereKey($media->getKey())->exists())->toBeTrue();

    shareListIndex(shareListActor(1), $media->getKey())
        ->assertOk()
        ->assertJsonPath('data.0.token_hash', $link->tokenHash);
});

// ──────────────────────────────────────────────────────────────────────────────
// D) Legacy compatibility — links issued before the registry existed
// ──────────────────────────────────────────────────────────────────────────────

it('keeps a pre-upgrade link with no registry row revocable, after which its URL answers 410', function (): void {
    Storage::fake('public');
    $media = shareListMedia('user', '1');
    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'legacy-bytes');

    $link = shareListIssue($media);
    ShareLink::query()->delete(); // pre-upgrade: the link exists only as a URL

    $this->get($link->url)->assertOk();

    $this->actingAs(shareListActor(1))
        ->postJson(route('file-manager.share.revoke'), ['media_id' => $media->getKey(), 'token' => $link->tokenHash])
        ->assertOk();

    $this->get($link->url)->assertStatus(410);
});
