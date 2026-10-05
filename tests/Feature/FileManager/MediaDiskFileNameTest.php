<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lvntr\StarterKit\Domain\Media\Actions\UploadMediaAction;
use Lvntr\StarterKit\Traits\HasMediaCollections;
use Spatie\MediaLibrary\HasMedia;

/*
|--------------------------------------------------------------------------
| Non-FileManager uploads are stored under a generated disk name
|--------------------------------------------------------------------------
|
| Avatar / form collections share one directory per model. With the client
| name on disk, a same-named upload overwrote the earlier file and deleting
| the replaced row then removed the new file as well.
|
*/

class DiskNameOwner extends Model implements HasMedia
{
    use HasMediaCollections;

    protected $table = 'test_owners';

    public $incrementing = false;

    protected $keyType = 'string';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('attachments');
    }
}

beforeEach(function (): void {
    Schema::create('test_owners', function (Blueprint $table): void {
        $table->uuid('id')->primary();
    });
});

function diskNameOwner(): DiskNameOwner
{
    $owner = (new DiskNameOwner)->forceFill(['id' => (string) Str::uuid()]);
    $owner->timestamps = false;
    $owner->save();

    return $owner;
}

function diskNameAvatarRequest(): Request
{
    $request = Request::create('/', 'POST');
    $request->files->set('avatar', UploadedFile::fake()->image('photo.jpg'));
    app()->instance('request', $request); // addMediaFromRequest() reads the container request

    return $request;
}

it('keeps the new avatar when it replaces one with the same client name', function (): void {
    $owner = diskNameOwner();
    $action = app(UploadMediaAction::class);

    // A fresh model per call, like two separate requests.
    $action->execute($owner, diskNameAvatarRequest(), 'avatar');
    $action->execute(DiskNameOwner::query()->findOrFail($owner->id), diskNameAvatarRequest(), 'avatar');

    $media = $owner->media()->where('collection_name', 'avatar')->get();

    expect($media)->toHaveCount(1)
        ->and($media[0]->name)->toBe('photo')
        ->and($media[0]->file_name)->not->toBe('photo.jpg')
        ->and($media[0]->file_name)->toEndWith('.jpg')
        ->and(Storage::disk('public')->exists($media[0]->getPathRelativeToRoot()))->toBeTrue();
});

it('stores same-named form uploads as separate files and shows the client name', function (): void {
    $owner = diskNameOwner();

    $owner->syncMediaCollection('attachments', [
        UploadedFile::fake()->create('report.pdf', 1, 'application/pdf'),
        UploadedFile::fake()->create('report.pdf', 1, 'application/pdf'),
    ]);

    $media = $owner->media()->where('collection_name', 'attachments')->get();
    $paths = $media->map->getPathRelativeToRoot()->unique();

    expect($paths)->toHaveCount(2)
        ->and($paths->every(fn (string $path) => Storage::disk('public')->exists($path)))->toBeTrue()
        ->and(collect($owner->load('media')->getMediaForForm('attachments'))->pluck('name')->all())
        ->toBe(['report.pdf', 'report.pdf']);
});
