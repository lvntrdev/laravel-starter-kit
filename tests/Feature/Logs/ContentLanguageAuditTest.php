<?php

use App\Models\ContentLanguage;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Domain\ContentLanguage\Actions\CreateContentLanguageAction;
use Lvntr\StarterKit\Domain\ContentLanguage\Actions\DeleteContentLanguageAction;
use Lvntr\StarterKit\Domain\ContentLanguage\Actions\UpdateContentLanguageAction;
use Lvntr\StarterKit\Domain\ContentLanguage\DTOs\ContentLanguageDTO;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Content-language audit sink — CRUD actions → activity_log (Task 8)
|--------------------------------------------------------------------------
|
| madde 3b: content-language create/update/delete must appear in the admin
| ActivityLog screen. Consistent with the other Task 8 surfaces, logging is
| action-side on the `audit` channel and is skipped when there is no
| authenticated causer (the seeder is not an audited admin action — which is
| why the existing ContentLanguageTest keeps passing without an activity_log
| table). This test proves one `audit` row per authenticated CRUD op.
|
| content_languages is built inline (as in ContentLanguageTest); activity_log
| is inline too.
|
*/

$stubs = dirname(__DIR__, 3).'/stubs';

if (! class_exists(ContentLanguage::class)) {
    require_once $stubs.'/app/Models/ContentLanguage.php';
}

beforeEach(function (): void {
    Schema::create('content_languages', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->string('native_name');
        $table->string('direction')->default('ltr');
        $table->string('flag')->nullable();
        $table->boolean('is_active')->default(true)->index();
        $table->boolean('is_default')->default(false);
        $table->string('fallback_code')->nullable();
        $table->integer('sort_order')->default(0)->index();
        $table->timestamps();
    });

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
    Schema::dropIfExists('content_languages');
});

function contentLanguageAuditActor(): Authenticatable
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Admin',
        'email' => 'cl-audit@example.test',
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

/**
 * @param  array<string, mixed>  $overrides
 */
function clAuditDto(array $overrides = []): ContentLanguageDTO
{
    return ContentLanguageDTO::fromArray($overrides + [
        'code' => 'en',
        'name' => 'English',
        'native_name' => 'English',
        'direction' => 'ltr',
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ]);
}

it('audits a created content language', function (): void {
    test()->actingAs(contentLanguageAuditActor());

    $language = app(CreateContentLanguageAction::class)->execute(clAuditDto(['code' => 'fr', 'name' => 'French', 'native_name' => 'Français']));

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(1)
        ->and($entry->event)->toBe('created')
        ->and($entry->description)->toBe('Content language created')
        ->and($entry->subject_id)->toBe($language->id)
        ->and($entry->getProperty('code'))->toBe('fr')
        ->and($entry->causer_id)->not->toBeNull();
});

it('audits an updated content language', function (): void {
    test()->actingAs(contentLanguageAuditActor());

    $language = app(CreateContentLanguageAction::class)->execute(clAuditDto(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch']));

    app(UpdateContentLanguageAction::class)->execute(
        $language,
        clAuditDto(['code' => 'de', 'name' => 'German (DE)', 'native_name' => 'Deutsch']),
    );

    $updated = Activity::query()->where('log_name', 'audit')->where('event', 'updated')->firstOrFail();

    expect($updated->description)->toBe('Content language updated')
        ->and($updated->getProperty('name'))->toBe('German (DE)');
});

it('audits a deleted content language', function (): void {
    test()->actingAs(contentLanguageAuditActor());

    $language = app(CreateContentLanguageAction::class)->execute(clAuditDto(['code' => 'it', 'name' => 'Italian', 'native_name' => 'Italiano']));

    app(DeleteContentLanguageAction::class)->execute($language);

    $deleted = Activity::query()->where('log_name', 'audit')->where('event', 'deleted')->firstOrFail();

    expect($deleted->description)->toBe('Content language deleted')
        ->and($deleted->getProperty('code'))->toBe('it');
});

it('does not audit content-language CRUD without an authenticated causer', function (): void {
    // No actingAs — mirrors the seeder path. This is precisely why the existing
    // ContentLanguageTest (no auth, no activity_log table) still passes.
    app(CreateContentLanguageAction::class)->execute(clAuditDto(['code' => 'es', 'name' => 'Spanish', 'native_name' => 'Español']));

    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(0);
});
