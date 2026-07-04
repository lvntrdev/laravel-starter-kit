<?php

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\FileManager\Actions\CreateShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\Actions\RevokeShareLinkAction;
use Lvntr\StarterKit\Domain\FileManager\DTOs\CreateShareLinkDTO;
use Lvntr\StarterKit\Tests\Stubs\TestMedia;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Share-link audit sink — Create/Revoke actions → activity_log (Task 8)
|--------------------------------------------------------------------------
|
| madde 3b: share-link create/revoke must appear in the admin ActivityLog
| screen with the media id + acting user. The signed URL and its
| signature/token hash are the share SECRET and must never reach the audit
| properties. This test proves:
|
|   1. CreateShareLinkAction writes one `audit` row (media id + owner), and
|      neither the signed URL nor the signature/token hash is in properties;
|   2. RevokeShareLinkAction writes one `audit` row (media id + revoker) on a
|      real revoke, and the token hash is not in properties;
|   3. an idempotent re-revoke does NOT write a second row;
|   4. an unauthenticated action call is not audited.
|
| The media/share tables come from DatabaseTestCase; activity_log is inline.
|
*/

beforeEach(function (): void {
    Schema::create('activity_log', function (Blueprint $table): void {
        $table->id();
        $table->string('log_name')->nullable()->index();
        $table->text('description');
        $table->nullableMorphs('subject', 'subject');
        $table->string('event')->nullable();
        $table->nullableMorphs('causer', 'causer');
        $table->json('attribute_changes')->nullable();
        $table->json('properties')->nullable();
        $table->timestamps();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('activity_log');
});

function shareAuditActor(): Authenticatable
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Admin',
        'email' => 'share-audit@example.test',
        'password' => 'x',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $actor = new class extends Model implements Authenticatable
    {
        use AuthenticatableTrait;

        protected $table = 'users';

        protected $guarded = [];

        public $timestamps = false;
    };

    return $actor->forceFill(['id' => $id]);
}

function insertShareAuditMedia(string $ownerId = 'owner-1'): TestMedia
{
    $id = DB::table('media')->insertGetId([
        'model_type' => 'user',
        'model_id' => $ownerId,
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => 'share-audit-'.Str::random(6),
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
        'deleted_at' => null,
    ]);

    return TestMedia::find($id);
}

it('audits a created share link with the media id and no url/signature secret', function (): void {
    test()->actingAs(shareAuditActor());

    $media = insertShareAuditMedia('owner-share');

    $result = app(CreateShareLinkAction::class)->execute(CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-share',
        'expires_in_hours' => 24,
    ]));

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    expect($entry->event)->toBe('created')
        ->and($entry->description)->toBe('Share link created')
        ->and($entry->getProperty('media_id'))->toBe($media->getKey())
        ->and($entry->getProperty('owner_id'))->toBe('owner-share');

    // The signed URL and its signature/token hash are the share secret and
    // must never appear in the audit properties.
    $serialized = json_encode($entry->properties);
    expect($serialized)->not->toContain($result->url)
        ->and($serialized)->not->toContain($result->tokenHash)
        ->and($serialized)->not->toContain('signature');
});

it('audits a revoked share link once with the revoker and no token hash', function (): void {
    $actor = shareAuditActor();
    test()->actingAs($actor);

    $media = insertShareAuditMedia('owner-revoke');
    $tokenHash = hash('sha256', 'some-signature-value');

    app(RevokeShareLinkAction::class)->execute(
        media: $media,
        tokenHash: $tokenHash,
        revokedByUserId: (string) $actor->getAuthIdentifier(),
    );

    $rows = Activity::query()->where('log_name', 'audit')->get();

    expect($rows)->toHaveCount(1);

    $entry = $rows->first();

    expect($entry->event)->toBe('deleted')
        ->and($entry->description)->toBe('Share link revoked')
        ->and($entry->getProperty('media_id'))->toBe($media->getKey())
        ->and($entry->getProperty('revoked_by_user_id'))->toBe((string) $actor->getAuthIdentifier());

    // The token hash is the revocation lookup key (a share-secret derivative)
    // and must not be logged.
    expect(json_encode($entry->properties))->not->toContain($tokenHash);
});

it('does not audit an idempotent re-revoke (no second row)', function (): void {
    $actor = shareAuditActor();
    test()->actingAs($actor);

    $media = insertShareAuditMedia('owner-idem');
    $tokenHash = hash('sha256', 'sig');

    app(RevokeShareLinkAction::class)->execute(media: $media, tokenHash: $tokenHash, revokedByUserId: (string) $actor->getAuthIdentifier());
    app(RevokeShareLinkAction::class)->execute(media: $media, tokenHash: $tokenHash, revokedByUserId: (string) $actor->getAuthIdentifier());

    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(1);
});

it('does not audit a share action when there is no authenticated causer', function (): void {
    $media = insertShareAuditMedia('owner-noauth');

    app(CreateShareLinkAction::class)->execute(CreateShareLinkDTO::fromArray([
        'media' => $media,
        'owner_type' => 'user',
        'owner_id' => 'owner-noauth',
    ]));

    app(RevokeShareLinkAction::class)->execute(media: $media, tokenHash: hash('sha256', 'sig'));

    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(0);
});
