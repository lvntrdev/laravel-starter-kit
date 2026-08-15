<?php

declare(strict_types=1);

use App\Http\Resources\Admin\Role\RoleResource;
use App\Http\Resources\Admin\User\UserResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Lvntr\StarterKit\Http\Resources\Admin\ApiClient\ApiClientResource;
use Lvntr\StarterKit\Http\Resources\Admin\ApiToken\ApiTokenResource;
use Lvntr\StarterKit\Http\Resources\Admin\ContentLanguage\ContentLanguageResource;
use Lvntr\StarterKit\Http\Resources\FileManager\ShareLinkResource;
use Lvntr\StarterKit\Tests\TestCase;

uses(TestCase::class);

require_once dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/User/UserResource.php';
require_once dirname(__DIR__, 3).'/stubs/app/Http/Resources/Admin/Role/RoleResource.php';

it('serializes every resource date as ISO-8601 with the resolved offset', function (): void {
    config([
        'app.display_timezone' => 'Europe/Istanbul',
        'app.timezone' => 'UTC',
    ]);

    $makeModel = static function (array $attributes): Model {
        $model = new class extends Model {};
        $model->setRawAttributes($attributes);

        return $model;
    };

    $request = Request::create('/', 'GET');
    $createdAt = '2026-03-14 05:36:00 UTC';
    $updatedAt = '2026-03-15 06:45:00 UTC';
    $expiresAt = '2026-03-16 07:54:00 UTC';

    $user = $makeModel([
        'id' => 1,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'full_name' => 'Ada Lovelace',
        'initials' => 'AL',
        'email' => 'ada@example.test',
        'status' => 'active',
        'avatar_url' => null,
        'timezone' => 'Europe/Istanbul',
        'email_verified_at' => $createdAt,
        'created_at' => $createdAt,
        'updated_at' => $updatedAt,
    ]);

    $payloads = [
        'UserResource' => [
            (new UserResource($user))->resolve($request),
            ['email_verified_at', 'created_at', 'updated_at'],
        ],
        'RoleResource' => [
            (new RoleResource($makeModel([
                'id' => 1,
                'name' => 'admin',
                'display_name' => 'Admin',
                'group' => 'system',
                'color' => 'primary',
                'sort_order' => 1,
                'guard_name' => 'web',
                'seeded_permissions' => [],
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ])))->resolve($request),
            ['created_at', 'updated_at'],
        ],
        'ContentLanguageResource' => [
            (new ContentLanguageResource($makeModel([
                'id' => 1,
                'code' => 'tr',
                'name' => 'Turkish',
                'native_name' => 'Türkçe',
                'direction' => 'ltr',
                'flag' => 'tr',
                'is_active' => true,
                'is_default' => true,
                'fallback_code' => null,
                'sort_order' => 1,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ])))->resolve($request),
            ['created_at', 'updated_at'],
        ],
        'ApiTokenResource' => [
            (new ApiTokenResource($makeModel([
                'id' => 'token-id',
                'name' => 'Test token',
                'scopes' => [],
                'revoked' => false,
                'expires_at' => $expiresAt,
                'created_at' => $createdAt,
                'user_id' => 1,
            ])))->resolve($request),
            ['expires_at', 'created_at'],
        ],
        'ApiClientResource' => [
            (new ApiClientResource($makeModel([
                'id' => 'client-id',
                'name' => 'Test client',
                'grant_types' => ['client_credentials'],
                'redirect_uris' => [],
                'revoked' => false,
                'secret' => 'hashed-secret',
                'plainSecret' => null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ])))->resolve($request),
            ['created_at', 'updated_at'],
        ],
        'ShareLinkResource' => [
            (new ShareLinkResource([
                'url' => 'https://example.test/share',
                'expires_at' => $expiresAt,
                'token_hash' => str_repeat('a', 64),
            ]))->resolve($request),
            ['expires_at'],
        ],
    ];

    foreach ($payloads as $resource => [$payload, $dateFields]) {
        foreach ($dateFields as $field) {
            $value = $payload[$field];

            expect($value, "{$resource}.{$field}")
                ->toBeString()
                ->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/')
                ->not->toMatch('/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}$/')
                ->and(DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value))
                ->toBeInstanceOf(DateTimeImmutable::class)
                ->and(substr($value, -6))
                ->toBe('+03:00');
        }
    }

    expect($payloads['UserResource'][0]['timezone'])->toBe('Europe/Istanbul');
});

it('uses the API date contract in the generated Resource scaffold', function (): void {
    $source = file_get_contents(dirname(__DIR__, 3).'/src/Console/Commands/MakeDomainCommand.php');
    $start = strpos($source, '    private function createAdminResource(): void');
    $end = strpos($source, '    // HELPERS', $start);
    $resourceScaffold = substr($source, $start, $end - $start);

    // Single-quoted on purpose: the scaffold is a heredoc, so the source
    // carries a LITERAL backslash before `$this`. A double-quoted needle
    // would collapse `\$` to `$` and never match what is on disk.
    expect($resourceScaffold)
        ->toContain('\'created_at\' => to_api_date(\$this->created_at),')
        ->toContain('\'updated_at\' => to_api_date(\$this->updated_at),')
        ->not->toContain('format_date(');
});
