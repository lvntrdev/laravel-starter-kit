<?php

/*
|--------------------------------------------------------------------------
| FileManager — folder cycle guards
|--------------------------------------------------------------------------
|
| `parent_id` is a plain self-referencing column: nothing in the schema stops
| it describing a ring. Two guards keep that from turning into an outage and
| they are tested apart on purpose.
|
|   A) Read side — FolderContentsQuery walks the children map with a visited
|      set, so a folder that already sits in a cycle returns a finite subtree
|      instead of looping. This holds whatever wrote the cycle, which is why
|      the fixture forges it with a raw UPDATE rather than through an action.
|
|   B) Write side — MoveItemAction runs its cycle check and the parent write
|      inside one transaction behind a row lock, and refuses a move into the
|      folder itself or into one of its own descendants.
|
| NOTE on (A): if the visited set regresses, the walk does not fail fast — it
| grows `$ids` until PHP hits its memory limit. The assertion below is on the
| finite values; the termination itself is what the run proves.
|
| NOTE on lock granularity: SQLite makes `lockForUpdate` a no-op, so nothing
| here claims the concurrency race is closed. These tests cover single-request
| correctness only; the residual window is named in MoveItemAction.
|
*/

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\FileManager\Actions\MoveItemAction;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Domain\FileManager\Queries\FolderContentsQuery;
use Lvntr\StarterKit\Exceptions\DomainRuleException;
use Lvntr\StarterKit\Tests\Stubs\TestFileFolder;
use Lvntr\StarterKit\Tests\Stubs\TestOwner;

function cycleContext(string $ownerId): FileManagerContextDTO
{
    $owner = new TestOwner;
    $owner->setAttribute('id', $ownerId);
    $owner->exists = false;

    return new FileManagerContextDTO(
        context: 'test-owner',
        contextId: $ownerId,
        owner: $owner,
        ownerType: 'test-owner',
        ownerId: $ownerId,
    );
}

function cycleFolder(string $ownerId, string $name, ?string $parentId = null): TestFileFolder
{
    return TestFileFolder::query()->create([
        'name' => $name,
        'parent_id' => $parentId,
        'owner_type' => 'test-owner',
        'owner_id' => $ownerId,
    ]);
}

/** Raw insert — the DTO/file pipeline is irrelevant, only folder_id + size are. */
function cycleMediaRow(string $ownerId, string $folderId, int $sizeBytes): void
{
    DB::table('media')->insert([
        'model_type' => 'test-owner',
        'model_id' => $ownerId,
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => 'cycle-'.Str::random(6),
        'file_name' => 'cycle.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'conversions_disk' => null,
        'size' => $sizeBytes,
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => null,
        'folder_id' => $folderId,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
    ]);
}

// ── A) read side — a cyclic graph must not loop ──────────────────────────────

it('returns a finite subtree when the folder already sits in a cycle', function (): void {
    $ownerId = (string) Str::uuid();
    $context = cycleContext($ownerId);

    $outer = cycleFolder($ownerId, 'Outer');
    $inner = cycleFolder($ownerId, 'Inner', $outer->id);

    // Forge the ring behind the action's back: Outer becomes a child of its
    // own child. Both call sites of collectSubtreeIds now start inside it.
    DB::table('file_folders')->where('id', $outer->id)->update(['parent_id' => $inner->id]);

    cycleMediaRow($ownerId, $inner->id, 1_024);

    $result = app(FolderContentsQuery::class)->execute($context, $outer->id);

    // Terminated. Each folder in the ring is counted once, not once per lap.
    expect($result['stats']['file_count'])->toBe(1)
        ->and($result['stats']['total_size'])->toBe(1_024)
        ->and($result['folders'])->toHaveCount(1)
        ->and($result['folders'][0]['id'])->toBe((string) $inner->id)
        ->and($result['folders'][0]['file_count'])->toBe(1)
        ->and($result['folders'][0]['total_size'])->toBe(1_024);
});

it('still walks a healthy subtree to full depth', function (): void {
    $ownerId = (string) Str::uuid();
    $context = cycleContext($ownerId);

    $root = cycleFolder($ownerId, 'Root');
    $mid = cycleFolder($ownerId, 'Mid', $root->id);
    $leaf = cycleFolder($ownerId, 'Leaf', $mid->id);

    cycleMediaRow($ownerId, $mid->id, 100);
    cycleMediaRow($ownerId, $leaf->id, 200);

    $result = app(FolderContentsQuery::class)->execute($context, $root->id);

    // The visited set must dedupe, not truncate: the grandchild still counts.
    expect($result['stats']['file_count'])->toBe(2)
        ->and($result['stats']['total_size'])->toBe(300)
        ->and($result['folders'][0]['file_count'])->toBe(2)
        ->and($result['folders'][0]['total_size'])->toBe(300);
});

// ── B) write side — a move may not create a cycle ────────────────────────────

it('refuses to move a folder into itself', function (): void {
    $ownerId = (string) Str::uuid();
    $context = cycleContext($ownerId);

    $folder = cycleFolder($ownerId, 'Invoices');

    expect(fn () => app(MoveItemAction::class)->execute($context, 'folder', $folder->id, $folder->id))
        ->toThrow(DomainRuleException::class);

    expect(TestFileFolder::query()->find($folder->id)->parent_id)->toBeNull();
});

it('refuses to move a folder into one of its own descendants', function (): void {
    $ownerId = (string) Str::uuid();
    $context = cycleContext($ownerId);

    $root = cycleFolder($ownerId, 'Root');
    $mid = cycleFolder($ownerId, 'Mid', $root->id);
    $leaf = cycleFolder($ownerId, 'Leaf', $mid->id);

    expect(fn () => app(MoveItemAction::class)->execute($context, 'folder', $root->id, $leaf->id))
        ->toThrow(DomainRuleException::class);

    // The whole chain is untouched — the write never ran.
    expect(TestFileFolder::query()->find($root->id)->parent_id)->toBeNull()
        ->and((string) TestFileFolder::query()->find($mid->id)->parent_id)->toBe((string) $root->id)
        ->and((string) TestFileFolder::query()->find($leaf->id)->parent_id)->toBe((string) $mid->id);
});

// ── B') the transaction must not break the moves that are legal ──────────────

it('moves a folder under an unrelated target and back to root', function (): void {
    $ownerId = (string) Str::uuid();
    $context = cycleContext($ownerId);

    $source = cycleFolder($ownerId, 'Source');
    $target = cycleFolder($ownerId, 'Target');

    app(MoveItemAction::class)->execute($context, 'folder', $source->id, $target->id);
    expect((string) TestFileFolder::query()->find($source->id)->parent_id)->toBe((string) $target->id);

    app(MoveItemAction::class)->execute($context, 'folder', $source->id, null);
    expect(TestFileFolder::query()->find($source->id)->parent_id)->toBeNull();
});

it('still refuses a move that collides with a sibling of the same name', function (): void {
    $ownerId = (string) Str::uuid();
    $context = cycleContext($ownerId);

    $target = cycleFolder($ownerId, 'Target');
    cycleFolder($ownerId, 'Reports', $target->id);
    $source = cycleFolder($ownerId, 'Reports');

    expect(fn () => app(MoveItemAction::class)->execute($context, 'folder', $source->id, $target->id))
        ->toThrow(DomainRuleException::class);

    expect(TestFileFolder::query()->find($source->id)->parent_id)->toBeNull();
});
