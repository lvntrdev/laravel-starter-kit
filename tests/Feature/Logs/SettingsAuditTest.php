<?php

use App\Models\Setting;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Domain\Setting\SettingService;
use Lvntr\StarterKit\Tests\Stubs\TestSetting;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| Settings audit sink — SettingService writes to activity_log (Task 8)
|--------------------------------------------------------------------------
|
| madde 3b: setting changes must appear in the admin ActivityLog screen,
| but a setting VALUE may be a secret (mail.password, storage.*_secret,
| turnstile/postman/apidog keys). This test proves:
|
|   1. setValue()/setGroup() write exactly one `audit` row per call, listing
|      only the changed KEY PATHS (never the values);
|   2. a sensitive key's plaintext value never reaches the properties;
|   3. an unauthenticated write (seeder/console/queue — no causer) is NOT
|      audited — the trail records attributed admin actions only.
|
| The `settings` table comes from DatabaseTestCase; activity_log is built
| inline (project Schema-builder convention). SettingService writes to the
| App\Models\Setting FQCN, aliased to the TestSetting stub here.
|
*/

if (! class_exists(Setting::class)) {
    class_alias(TestSetting::class, Setting::class);
}

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

/**
 * Minimal Authenticatable actor on the `users` table so activity()'s causer
 * resolver returns a real user (auth()->check() === true).
 */
function settingsAuditActor(): Authenticatable
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Admin',
        'email' => 'settings-audit@example.test',
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

it('records a single audit row listing only the changed key on setValue', function (): void {
    test()->actingAs(settingsAuditActor());

    app(SettingService::class)->setValue('general.site_name', 'Acme Corp');

    $rows = Activity::query()->where('log_name', 'audit')->get();

    expect($rows)->toHaveCount(1);

    $entry = $rows->first();

    expect($entry->event)->toBe('updated')
        ->and($entry->description)->toBe('Settings updated')
        ->and($entry->getProperty('keys'))->toBe(['general.site_name'])
        ->and($entry->causer_id)->not->toBeNull();
});

it('never writes a sensitive setting value into the audit properties', function (): void {
    test()->actingAs(settingsAuditActor());

    $secret = 'super-secret-mail-password-value';

    app(SettingService::class)->setValue('mail.password', $secret);

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    // The key is recorded...
    expect($entry->getProperty('keys'))->toBe(['mail.password']);

    // ...but the value appears NOWHERE in the serialized properties.
    expect(json_encode($entry->properties))->not->toContain($secret);
});

it('records every changed key path on setGroup', function (): void {
    test()->actingAs(settingsAuditActor());

    app(SettingService::class)->setGroup('general', [
        'site_name' => 'Acme',
        'tagline' => 'We build things',
    ]);

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    expect($entry->event)->toBe('updated')
        ->and($entry->getProperty('keys'))->toBe(['general.site_name', 'general.tagline']);
});

it('does NOT audit a setting write when there is no authenticated causer', function (): void {
    // No actingAs — a seeder/console/queue write. auth()->check() === false.
    app(SettingService::class)->setValue('general.site_name', 'Acme');

    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(0);
});
