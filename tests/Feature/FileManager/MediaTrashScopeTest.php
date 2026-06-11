<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Tests\Stubs\TestMedia;

/*
|--------------------------------------------------------------------------
| Media trash kapsamı — yalnızca FileManager 'files' koleksiyonu
|--------------------------------------------------------------------------
|
| Çöp kutusu YALNIZCA FileManager 'files' koleksiyonuna aittir. Avatar,
| logo, form eki gibi diğer koleksiyonlarda delete() kalıcı silmeye
| (forceDelete) çevrilir: satır DB'den gider, fiziksel dosya Spatie
| observer'ıyla diskten silinir. Aksi hâlde bu kayıtlar trash UI'da
| görünmeyen, purge edilmeyen ve kotayı şişiren artıklara dönüşür.
|
| Davranış TestMedia üzerinden test edilir (consumer stub Media'nın
| birebir ikizi); stub senkronu içerik-assertion ile ayrıca korunur.
|
*/

/**
 * Test fixture: media satırı + diske gerçek dosya yazar.
 *
 * @return TestMedia model instance (withTrashed üzerinden yüklenir)
 */
function makeMediaWithFile(string $collection, bool $trashed = false): TestMedia
{
    $id = DB::table('media')->insertGetId([
        'model_type' => 'test-owner',
        'model_id' => (string) Str::uuid(),
        'uuid' => Str::uuid()->toString(),
        'collection_name' => $collection,
        'name' => 'scope-'.Str::random(6),
        'file_name' => 'scope-test.pdf',
        'mime_type' => 'application/pdf',
        'disk' => 'public',
        'conversions_disk' => null,
        'size' => 512,
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

    /** @var TestMedia $media */
    $media = TestMedia::withTrashed()->find($id);
    Storage::disk('public')->put($media->getPathRelativeToRoot(), 'dummy-content');

    return $media;
}

// ──────────────────────────────────────────────────────────────────────────────
// A) FileManager dışı koleksiyonlar — delete() kalıcıdır
// ──────────────────────────────────────────────────────────────────────────────

it('permanently deletes non-FileManager media on delete()', function (string $collection): void {
    $media = makeMediaWithFile($collection);
    $path = $media->getPathRelativeToRoot();

    $media->delete();

    // Satır tamamen gitti — trashed olarak bile durmuyor.
    expect(TestMedia::withTrashed()->find($media->id))->toBeNull();
    // Fiziksel dosya da diskten silindi (Spatie observer, force delete yolu).
    expect(Storage::disk('public')->exists($path))->toBeFalse();
})->with(['avatar', 'logo', 'image']);

it('permanently deletes non-FileManager media even while trash is enabled', function (): void {
    config()->set('file-manager.settings.enable_trash', true);

    $media = makeMediaWithFile('avatar');
    $media->delete();

    expect(TestMedia::withTrashed()->find($media->id))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// B) FileManager 'files' koleksiyonu — trash davranışı korunur
// ──────────────────────────────────────────────────────────────────────────────

it('soft deletes files-collection media into the trash while trash is enabled', function (): void {
    config()->set('file-manager.settings.enable_trash', true);

    $media = makeMediaWithFile('files');
    $path = $media->getPathRelativeToRoot();

    $media->delete();

    // Satır trash'te: normal scope görmez, withTrashed görür.
    expect(TestMedia::find($media->id))->toBeNull();
    $trashed = TestMedia::withTrashed()->find($media->id);
    expect($trashed)->not->toBeNull();
    expect($trashed->trashed())->toBeTrue();
    // Fiziksel dosya diskte KALIR — restore edilebilir.
    expect(Storage::disk('public')->exists($path))->toBeTrue();
});

it('hard deletes files-collection media when trash is disabled', function (): void {
    config()->set('file-manager.settings.enable_trash', false);

    $media = makeMediaWithFile('files');
    $path = $media->getPathRelativeToRoot();

    $media->delete();

    expect(TestMedia::withTrashed()->find($media->id))->toBeNull();
    expect(Storage::disk('public')->exists($path))->toBeFalse();
});

it('forceDelete() passes through without recursion for files-collection media', function (): void {
    config()->set('file-manager.settings.enable_trash', true);

    $media = makeMediaWithFile('files');
    $media->forceDelete();

    expect(TestMedia::withTrashed()->find($media->id))->toBeNull();
});

it('restored files-collection media goes back to the trash on a second delete', function (): void {
    config()->set('file-manager.settings.enable_trash', true);

    $media = makeMediaWithFile('files');
    $media->delete();

    $trashed = TestMedia::withTrashed()->find($media->id);
    $trashed->restore();
    expect(TestMedia::find($media->id))->not->toBeNull();

    $trashed->delete();
    expect(TestMedia::withTrashed()->find($media->id)?->trashed())->toBeTrue();
});

// ──────────────────────────────────────────────────────────────────────────────
// C) purge-trash — legacy non-files artıklarını yaş şartsız süpürür
// ──────────────────────────────────────────────────────────────────────────────

it('purge-trash sweeps orphaned non-files trashed media regardless of age and keeps fresh files trash', function (): void {
    // Legacy artık: eski sürümde soft-delete'e düşmüş avatar (taze trashed —
    // yaş şartı uygulanMAmalı). Yeni davranışta bu satır oluşamayacağı için
    // model delete'i yerine doğrudan DB durumu simüle edilir.
    $orphan = makeMediaWithFile('avatar', trashed: true);

    // Taze files trash kaydı — retention dolmadığı için KALMALI.
    $freshFile = makeMediaWithFile('files', trashed: true);

    // Retention'ı aşmış files trash kaydı — purge edilmeli.
    $oldFile = makeMediaWithFile('files', trashed: true);
    DB::table('media')->where('id', $oldFile->id)->update(['deleted_at' => now()->subDays(30)]);

    $this->artisan('file-manager:purge-trash', ['--days' => 7])->assertSuccessful();

    expect(TestMedia::withTrashed()->find($orphan->id))->toBeNull();
    expect(TestMedia::withTrashed()->find($freshFile->id))->not->toBeNull();
    expect(TestMedia::withTrashed()->find($oldFile->id))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// D) Stub senkron guard'ı — consumer Media stub'ı aynı davranışı taşımalı
// ──────────────────────────────────────────────────────────────────────────────

it('consumer Media stub carries the collection-scoped trash override', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 3).'/stubs/app/Models/Media.php');

    expect($contents)->toContain('shouldMoveToTrash');
    expect($contents)->toContain('forceDeleting');
    expect($contents)->toContain("FILE_MANAGER_COLLECTION = 'files'");
    expect($contents)->toContain('enable_trash');
});
