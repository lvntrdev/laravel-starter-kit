<?php

use App\Models\User;
use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Laravel\Passport\PersonalAccessTokenResult;
use Lvntr\StarterKit\Domain\ApiClient\Actions\CreateApiClientAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\CreatePersonalAccessTokenAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\RevokeApiClientAction;
use Lvntr\StarterKit\Domain\ApiClient\Actions\RevokeApiTokenAction;
use Spatie\Activitylog\Models\Activity;

/*
|--------------------------------------------------------------------------
| API client/token audit sink — Passport actions → activity_log (Task 8)
|--------------------------------------------------------------------------
|
| madde 3b: OAuth client + personal-access-token create/revoke must appear in
| the admin ActivityLog screen. The client secret (plainSecret) and the PAT
| access token are one-time secrets and must NEVER reach the audit
| properties. This test proves, behaviourally:
|
|   1. CreateApiClientAction writes one `audit` row (client id + grant type)
|      with no secret in properties;
|   2. RevokeApiClientAction / RevokeApiTokenAction each write one `audit` row;
|   3. CreatePersonalAccessTokenAction writes one `audit` row and the access
|      token value never appears in properties (createToken() is stubbed here
|      so the test needs no JWT signing keys — the action's LOGGING is the
|      unit under test, not Passport's token machinery);
|   4. an unauthenticated call is not audited.
|
| The oauth_* tables + activity_log are built inline (Schema-builder convention).
|
*/

if (! class_exists(User::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Models/User.php';
}

beforeEach(function (): void {
    Schema::create('oauth_clients', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->nullableMorphs('owner');
        $table->string('name');
        $table->string('secret')->nullable();
        $table->string('provider')->nullable();
        $table->text('redirect_uris');
        $table->text('grant_types');
        $table->boolean('revoked');
        $table->timestamps();
    });

    Schema::create('oauth_access_tokens', function (Blueprint $table): void {
        $table->char('id', 80)->primary();
        $table->foreignId('user_id')->nullable()->index();
        $table->uuid('client_id');
        $table->string('name')->nullable();
        $table->text('scopes')->nullable();
        $table->boolean('revoked');
        $table->timestamps();
        $table->dateTime('expires_at')->nullable();
    });

    Schema::create('oauth_refresh_tokens', function (Blueprint $table): void {
        $table->char('id', 80)->primary();
        $table->char('access_token_id', 80)->index();
        $table->boolean('revoked');
        $table->dateTime('expires_at')->nullable();
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
    Schema::dropIfExists('oauth_refresh_tokens');
    Schema::dropIfExists('oauth_access_tokens');
    Schema::dropIfExists('oauth_clients');
});

function apiAuditActor(): Authenticatable
{
    $id = DB::table('users')->insertGetId([
        'name' => 'Admin',
        'email' => 'api-audit@example.test',
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

it('audits a created OAuth client with no secret in properties', function (): void {
    test()->actingAs(apiAuditActor());

    $client = app(CreateApiClientAction::class)->execute(
        name: 'Reporting service',
        grantType: 'client_credentials',
    );

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    expect($entry->event)->toBe('created')
        ->and($entry->description)->toBe('OAuth client created')
        ->and($entry->getProperty('client_id'))->toBe($client->id)
        ->and($entry->getProperty('grant_type'))->toBe('client_credentials');

    // No secret — neither the stored/plain secret value nor a secret-ish key.
    $serialized = json_encode($entry->properties);
    expect($serialized)->not->toContain((string) $client->secret)
        ->and($serialized)->not->toContain('secret');
});

it('audits a revoked OAuth client', function (): void {
    test()->actingAs(apiAuditActor());

    $client = app(CreateApiClientAction::class)->execute(name: 'To revoke', grantType: 'client_credentials');

    // Only assert on the revoke row.
    Activity::query()->delete();

    app(RevokeApiClientAction::class)->execute($client);

    $rows = Activity::query()->where('log_name', 'audit')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->event)->toBe('deleted')
        ->and($rows->first()->description)->toBe('OAuth client revoked')
        ->and($rows->first()->getProperty('client_id'))->toBe($client->id);
});

it('audits a revoked personal access token with no token value in properties', function (): void {
    test()->actingAs(apiAuditActor());

    $clientId = (string) Str::uuid();
    DB::table('oauth_clients')->insert([
        'id' => $clientId,
        'name' => 'PAT client',
        'secret' => null,
        'provider' => null,
        'redirect_uris' => '[]',
        'grant_types' => '["personal_access"]',
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('oauth_access_tokens')->insert([
        'id' => 'access-token-id-1',
        'user_id' => 7,
        'client_id' => $clientId,
        'name' => 'CI token',
        'scopes' => '[]',
        'revoked' => false,
        'created_at' => now(),
        'updated_at' => now(),
        'expires_at' => now()->addYear(),
    ]);

    $token = Passport::token()->newQuery()->findOrFail('access-token-id-1');

    app(RevokeApiTokenAction::class)->execute($token);

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    expect($entry->event)->toBe('deleted')
        ->and($entry->description)->toBe('API token revoked')
        ->and($entry->getProperty('token_id'))->toBe('access-token-id-1')
        ->and($entry->getProperty('user_id'))->toBe(7);

    // The token row is now revoked.
    expect(DB::table('oauth_access_tokens')->where('id', 'access-token-id-1')->value('revoked'))->toBe(1);
});

it('audits a created personal access token and never logs the access-token value', function (): void {
    $actor = apiAuditActor();
    test()->actingAs($actor);

    // Insert the access-token row that the stubbed result resolves via getToken().
    $clientId = (string) Str::uuid();
    DB::table('oauth_clients')->insert([
        'id' => $clientId, 'name' => 'PAT client', 'secret' => null, 'provider' => null,
        'redirect_uris' => '[]', 'grant_types' => '["personal_access"]', 'revoked' => false,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('oauth_access_tokens')->insert([
        'id' => 'new-pat-id', 'user_id' => 42, 'client_id' => $clientId, 'name' => 'My PAT',
        'scopes' => '["files.read"]', 'revoked' => false,
        'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addYear(),
    ]);

    $plainAccessToken = 'PLAINTEXT-ACCESS-TOKEN-SECRET-abc123';

    // A User whose createToken() bypasses Passport's JWT machinery and returns
    // a result carrying the plaintext access token (as the real one would).
    $user = new class extends User
    {
        public string $plain;

        public function createToken(string $name, array $scopes = [], ?DateTimeInterface $expiresAt = null): PersonalAccessTokenResult
        {
            return new PersonalAccessTokenResult([
                'accessToken' => $this->plain,
                'accessTokenId' => 'new-pat-id',
            ]);
        }
    };
    $user->plain = $plainAccessToken;
    $user->forceFill(['id' => 42]);

    app(CreatePersonalAccessTokenAction::class)->execute(
        user: $user,
        name: 'My PAT',
        scopes: ['files.read'],
    );

    $entry = Activity::query()->where('log_name', 'audit')->firstOrFail();

    expect($entry->event)->toBe('created')
        ->and($entry->description)->toBe('Personal access token created')
        ->and($entry->getProperty('token_id'))->toBe('new-pat-id')
        ->and($entry->getProperty('user_id'))->toBe(42)
        ->and($entry->getProperty('scopes'))->toBe(['files.read']);

    // The plaintext access token must appear NOWHERE in the properties.
    expect(json_encode($entry->properties))->not->toContain($plainAccessToken);
});

it('does not audit an API client/token action without an authenticated causer', function (): void {
    $client = app(CreateApiClientAction::class)->execute(name: 'No auth', grantType: 'client_credentials');
    app(RevokeApiClientAction::class)->execute($client);

    expect(Activity::query()->where('log_name', 'audit')->count())->toBe(0);
});
