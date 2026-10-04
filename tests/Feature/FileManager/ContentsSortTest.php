<?php

/*
|--------------------------------------------------------------------------
| FileManager — favorites / trash server-side sorting
|--------------------------------------------------------------------------
|
| `favorites/contents` and `trash/contents` accept the same `sort` /
| `direction` parameters as the folder view. Without `sort` the order must be
| exactly what it was before the parameters existed (favorites: name asc,
| trash: newest-deleted first). Driven through the controller with the real
| authorizer and the same `global`-context / single-permission actor setup as
| FileManagerAbilityMatrixTest — no route layer, no Spatie tables.
|
*/

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\FileManager\DTOs\FileManagerContextDTO;
use Lvntr\StarterKit\Domain\FileManager\Queries\FavoritesContentsQuery;
use Lvntr\StarterKit\Domain\FileManager\Queries\TrashContentsQuery;
use Lvntr\StarterKit\Domain\FileManager\Services\FileManagerAuthorizer;
use Lvntr\StarterKit\Domain\FileManager\Support\ContextRegistry;
use Lvntr\StarterKit\Http\Controllers\FileManagerController;
use Lvntr\StarterKit\Http\Requests\FileManager\FileManagerContextRequest;
use Lvntr\StarterKit\Tests\Stubs\TestFileFavorite;
use Lvntr\StarterKit\Tests\Stubs\TestFileFolder;
use Lvntr\StarterKit\Tests\Stubs\TestOwner;

// ──────────────────────────────────────────────────────────────────────────────
// Fixtures
// ──────────────────────────────────────────────────────────────────────────────

function sortReader(): Authenticatable
{
    $actor = new class extends Model implements Authenticatable
    {
        use AuthenticatableTrait;

        protected $table = 'fm_sort_actors';

        public $timestamps = false;

        public function can(mixed $abilities, mixed $arguments = []): bool
        {
            return $abilities === 'files.read';
        }
    };

    return $actor->forceFill(['id' => 1]);
}

function sortContext(): FileManagerContextDTO
{
    $owner = (new TestOwner)->forceFill(['id' => 'sort-bucket']);

    return new FileManagerContextDTO(
        context: 'global',
        contextId: null,
        owner: $owner,
        ownerType: $owner->getMorphClass(),
        ownerId: 'sort-bucket',
    );
}

/**
 * @param  array<string, string>  $query
 */
function sortRequest(array $query): FileManagerContextRequest
{
    $request = new class extends FileManagerContextRequest
    {
        public function context(): FileManagerContextDTO
        {
            return sortContext();
        }
    };

    return $request::create('/file-manager/contents', 'GET', $query);
}

/**
 * @return array{name: string, size: int, created: string, deleted: ?string}
 */
function sortSpec(string $name, int $size, string $created, ?string $deleted = null): array
{
    return ['name' => $name, 'size' => $size, 'created' => $created, 'deleted' => $deleted];
}

/**
 * @param  array{name: string, size: int, created: string, deleted: ?string}  $spec
 */
function sortInsertFile(array $spec): int
{
    return DB::table('media')->insertGetId([
        'model_type' => sortContext()->ownerType,
        'model_id' => 'sort-bucket',
        'uuid' => Str::uuid()->toString(),
        'collection_name' => 'files',
        'name' => $spec['name'],
        'file_name' => $spec['name'].'.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'conversions_disk' => null,
        'size' => $spec['size'],
        'manipulations' => '[]',
        'custom_properties' => '[]',
        'generated_conversions' => '[]',
        'responsive_images' => '[]',
        'order_column' => null,
        'folder_id' => null,
        'created_at' => $spec['created'],
        'updated_at' => $spec['created'],
        'deleted_at' => $spec['deleted'],
    ]);
}

function sortFavorite(string $type, string|int $id): void
{
    TestFileFavorite::query()->create([
        'owner_type' => sortContext()->ownerType,
        'owner_id' => 'sort-bucket',
        'favoritable_type' => $type,
        'favoritable_id' => (string) $id,
    ]);
}

function sortFolder(string $name, ?string $deleted = null): TestFileFolder
{
    $folder = TestFileFolder::query()->create([
        'name' => $name,
        'owner_type' => sortContext()->ownerType,
        'owner_id' => 'sort-bucket',
    ]);

    if ($deleted !== null) {
        $folder->forceFill(['deleted_at' => $deleted])->saveQuietly();
    }

    return $folder;
}

/**
 * Run a controller handler as a read-only actor and return the envelope data.
 *
 * @param  array<string, string>  $query
 * @return array<string, mixed>
 */
function sortCall(string $handler, array $query): array
{
    test()->actingAs(sortReader());

    $controller = new FileManagerController(new FileManagerAuthorizer(new ContextRegistry));
    $queryObject = $handler === 'favoritesContents' ? new FavoritesContentsQuery : new TrashContentsQuery;

    $request = sortRequest($query);

    return $controller->{$handler}($request, $queryObject)->toResponse($request)->getData(true)['data'];
}

/**
 * @param  array<string, mixed>  $data
 * @return list<string>
 */
function sortNames(array $data, string $list): array
{
    return array_values(array_map(fn (array $row): string => (string) $row['name'], $data[$list]));
}

/**
 * @param  list<array{name: string, size: int, created: string, deleted: ?string}>  $specs
 */
function sortFavoriteFiles(array $specs): void
{
    foreach ($specs as $spec) {
        sortFavorite('file', sortInsertFile($spec));
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Favorites
// ──────────────────────────────────────────────────────────────────────────────

it('orders favorite files by size descending', function (): void {
    sortFavoriteFiles([sortSpec('b', 200, '2026-01-02 00:00:00'), sortSpec('a', 100, '2026-01-03 00:00:00'), sortSpec('c', 300, '2026-01-01 00:00:00')]);

    $data = sortCall('favoritesContents', ['sort' => 'size', 'direction' => 'desc']);

    expect(sortNames($data, 'files'))->toBe(['c', 'b', 'a']);
});

it('orders favorite files by date ascending', function (): void {
    sortFavoriteFiles([sortSpec('b', 200, '2026-01-02 00:00:00'), sortSpec('a', 100, '2026-01-03 00:00:00'), sortSpec('c', 300, '2026-01-01 00:00:00')]);

    $data = sortCall('favoritesContents', ['sort' => 'date', 'direction' => 'asc']);

    expect(sortNames($data, 'files'))->toBe(['c', 'b', 'a']);
});

it('orders favorite files by name descending', function (): void {
    sortFavoriteFiles([sortSpec('b', 200, '2026-01-02 00:00:00'), sortSpec('a', 100, '2026-01-03 00:00:00'), sortSpec('c', 300, '2026-01-01 00:00:00')]);

    $data = sortCall('favoritesContents', ['sort' => 'name', 'direction' => 'desc']);

    expect(sortNames($data, 'files'))->toBe(['c', 'b', 'a']);
});

it('keeps favorites name-ascending when no sort is sent', function (): void {
    sortFavoriteFiles([sortSpec('b', 100, '2026-01-02 00:00:00'), sortSpec('c', 300, '2026-01-01 00:00:00'), sortSpec('a', 200, '2026-01-03 00:00:00')]);

    expect(sortNames(sortCall('favoritesContents', []), 'files'))->toBe(['a', 'b', 'c']);
});

it('falls back to name for an invalid favorites sort key', function (): void {
    sortFavoriteFiles([sortSpec('b', 100, '2026-01-02 00:00:00'), sortSpec('c', 300, '2026-01-01 00:00:00'), sortSpec('a', 200, '2026-01-03 00:00:00')]);

    $data = sortCall('favoritesContents', ['sort' => 'bogus; drop table media', 'direction' => 'sideways']);

    expect(sortNames($data, 'files'))->toBe(['a', 'b', 'c']);
});

it('keeps favorite folders name-ordered whatever the sort', function (): void {
    foreach (['zeta', 'alpha', 'mid'] as $name) {
        sortFavorite('folder', sortFolder($name)->getKey());
    }

    $data = sortCall('favoritesContents', ['sort' => 'size', 'direction' => 'desc']);

    expect(sortNames($data, 'folders'))->toBe(['alpha', 'mid', 'zeta']);
});

// ──────────────────────────────────────────────────────────────────────────────
// Trash
// ──────────────────────────────────────────────────────────────────────────────

it('keeps trash deleted-first when no sort is sent', function (): void {
    sortInsertFile(sortSpec('old', 300, '2026-01-01 00:00:00', '2026-02-01 00:00:00'));
    sortInsertFile(sortSpec('new', 100, '2026-01-02 00:00:00', '2026-03-01 00:00:00'));
    sortInsertFile(sortSpec('mid', 200, '2026-01-03 00:00:00', '2026-02-15 00:00:00'));
    sortFolder('f-old', '2026-02-01 00:00:00');
    sortFolder('f-new', '2026-03-01 00:00:00');

    // `direction` alone must not switch sorting on.
    $data = sortCall('trashContents', ['direction' => 'asc']);

    expect(sortNames($data, 'files'))->toBe(['new', 'mid', 'old'])
        ->and(sortNames($data, 'folders'))->toBe(['f-new', 'f-old']);
});

it('sorts trashed files by size and keeps trashed folders name-ordered', function (): void {
    sortInsertFile(sortSpec('old', 300, '2026-01-01 00:00:00', '2026-02-01 00:00:00'));
    sortInsertFile(sortSpec('new', 100, '2026-01-02 00:00:00', '2026-03-01 00:00:00'));
    sortInsertFile(sortSpec('mid', 200, '2026-01-03 00:00:00', '2026-02-15 00:00:00'));
    sortFolder('zeta', '2026-03-01 00:00:00');
    sortFolder('alpha', '2026-02-01 00:00:00');

    $desc = sortCall('trashContents', ['sort' => 'size', 'direction' => 'desc']);
    $asc = sortCall('trashContents', ['sort' => 'size', 'direction' => 'asc']);

    expect(sortNames($desc, 'files'))->toBe(['old', 'mid', 'new'])
        ->and(sortNames($asc, 'files'))->toBe(['new', 'mid', 'old'])
        ->and(sortNames($desc, 'folders'))->toBe(['alpha', 'zeta']);
});

it('falls back to name for an invalid trash sort key', function (): void {
    sortInsertFile(sortSpec('b', 100, '2026-01-02 00:00:00', '2026-03-01 00:00:00'));
    sortInsertFile(sortSpec('c', 300, '2026-01-01 00:00:00', '2026-02-01 00:00:00'));
    sortInsertFile(sortSpec('a', 200, '2026-01-03 00:00:00', '2026-02-15 00:00:00'));

    $data = sortCall('trashContents', ['sort' => 'nonsense']);

    expect(sortNames($data, 'files'))->toBe(['a', 'b', 'c']);
});

it('still hides trashed children of a trashed folder when sorting', function (): void {
    $parent = sortFolder('parent', '2026-02-01 00:00:00');
    $child = sortFolder('child', '2026-02-01 00:00:00');
    $child->forceFill(['parent_id' => $parent->getKey()])->saveQuietly();

    $data = sortCall('trashContents', ['sort' => 'date', 'direction' => 'desc']);

    expect(sortNames($data, 'folders'))->toBe(['parent']);
});
