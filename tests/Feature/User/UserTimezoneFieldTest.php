<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Domain\Role\Queries\RoleSelectOptionsQuery;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Lvntr\StarterKit\Domain\User\DTOs\UserDTO;
use Spatie\Permission\PermissionRegistrar;

if (! class_exists(User::class)) {
    require_once dirname(__DIR__, 3).'/stubs/app/Models/User.php';
}

require_once dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/User/StoreUserRequest.php';
require_once dirname(__DIR__, 3).'/stubs/app/Http/Requests/Admin/User/UpdateUserRequest.php';
require_once dirname(__DIR__, 3).'/stubs/app/Actions/Fortify/UpdateUserProfileInformation.php';
require_once dirname(__DIR__, 3).'/stubs/app/Http/Middleware/HandleInertiaRequests.php';

/**
 * The consumer User model uses UUIDs, so exercise it against a matching table
 * instead of DatabaseTestCase's intentionally minimal integer-key users shim.
 *
 * @return class-string<User>
 */
function timezoneUserClass(): string
{
    static $class = null;

    if ($class === null) {
        $class = (new class extends User
        {
            protected $table = 'timezone_test_users';
        })::class;
    }

    return $class;
}

/**
 * @param  class-string<StoreUserRequest|UpdateUserRequest>  $requestClass
 */
function timezoneRequestValidator(string $requestClass, mixed $timezone): Illuminate\Contracts\Validation\Validator
{
    $actor = new (timezoneUserClass());
    $actor->forceFill(['id' => '10000000-0000-0000-0000-000000000001']);

    $request = $requestClass::create('/', 'POST', [
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.test',
        'password' => 'Valid-Password-1!',
        'password_confirmation' => 'Valid-Password-1!',
        'status' => 'active',
        'role' => 'editor',
        'timezone' => $timezone,
    ]);
    $request->setContainer(app());
    $request->setUserResolver(fn (): User => $actor);
    $request->setRouteResolver(fn (): object => new class($actor)
    {
        public function __construct(private readonly User $user) {}

        public function parameter(string $key, mixed $default = null): mixed
        {
            return $key === 'user' ? $this->user : $default;
        }
    });

    $prepare = new ReflectionMethod($request, 'prepareForValidation');
    $prepare->invoke($request);

    return Validator::make($request->all(), $request->rules());
}

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    config(['permission' => require dirname(__DIR__, 3).'/vendor/spatie/laravel-permission/config/permission.php']);
    app()->forgetInstance(PermissionRegistrar::class);

    Schema::create('timezone_test_users', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamp('password_changed_at')->nullable();
        $table->string('status')->default('active');
        $table->string('timezone', 64)->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    Schema::create('roles', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('permissions', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_roles', function (Blueprint $table): void {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->uuid('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->uuid('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table): void {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });

    app()->instance(RoleSelectOptionsQuery::class, new class extends RoleSelectOptionsQuery
    {
        public function get(User $user): array
        {
            return [['label' => 'Editor', 'value' => 'editor', 'color' => null]];
        }
    });
});

it('persists a valid timezone supplied through the admin create contract', function (): void {
    $validator = timezoneRequestValidator(StoreUserRequest::class, 'Europe/Istanbul');

    expect($validator->passes())->toBeTrue();

    $user = timezoneUserClass()::create(UserDTO::fromArray($validator->validated())->toArray());

    expect($user->refresh()->timezone)->toBe('Europe/Istanbul');
});

it('rejects an invalid timezone in both admin user requests', function (string $requestClass): void {
    $validator = timezoneRequestValidator($requestClass, 'Mars/Olympus_Mons');

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('timezone'))->toBeTrue();
})->with([
    'store request' => StoreUserRequest::class,
    'update request' => UpdateUserRequest::class,
]);

it('normalizes an empty admin selection and persists it as null', function (): void {
    $validator = timezoneRequestValidator(StoreUserRequest::class, '');

    expect($validator->passes())->toBeTrue()
        ->and($validator->validated()['timezone'])->toBeNull();

    $user = timezoneUserClass()::create(UserDTO::fromArray($validator->validated())->toArray());

    expect($user->refresh()->timezone)->toBeNull();
});

it('saves and normalizes timezone through the Fortify profile action', function (): void {
    $user = timezoneUserClass()::create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.test',
        'password' => 'Valid-Password-1!',
        'timezone' => null,
    ]);

    $action = app(UpdateUserProfileInformation::class);
    $action->update($user, [
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.test',
        'timezone' => 'Asia/Tokyo',
    ]);

    expect($user->refresh()->timezone)->toBe('Asia/Tokyo');

    $action->update($user, [
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.test',
        'timezone' => '',
    ]);

    expect($user->refresh()->timezone)->toBeNull();
});

it('leaves a stored timezone alone when the profile payload omits the field', function (): void {
    // An omitted field means "leave it alone"; only a submitted empty value
    // means "follow the site default". Every profile form that predates the
    // timezone selector — and any API client posting just name and email —
    // goes through this path, so treating a missing key as null would wipe
    // the preference on the next unrelated profile save.
    $user = timezoneUserClass()::create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.test',
        'password' => 'Valid-Password-1!',
        'timezone' => 'Asia/Tokyo',
    ]);

    app(UpdateUserProfileInformation::class)->update($user, [
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
        'email' => 'grace@example.test',
    ]);

    expect($user->refresh()->timezone)->toBe('Asia/Tokyo');
});

it('shares the resolved timezone and the raw user preference', function (): void {
    config([
        'app.display_timezone' => 'Europe/Berlin',
        'app.timezone' => 'UTC',
    ]);

    $user = timezoneUserClass()::create([
        'first_name' => 'Site',
        'last_name' => 'Follower',
        'email' => 'site-follower@example.test',
        'password' => 'Valid-Password-1!',
        'timezone' => null,
    ]);

    $request = Request::create('/dashboard');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn (): User => $user);

    $props = app(HandleInertiaRequests::class)->share($request);

    expect($props['timezone'])->toBe('Europe/Berlin')
        ->and($props['auth']['user']['timezone'])->toBeNull();
});

it('shares the configured installer timezone without querying the database', function (): void {
    config(['app.display_timezone' => 'Europe/Paris']);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $request = Request::create('/install');
    $request->setLaravelSession(app('session.store'));

    $props = app(HandleInertiaRequests::class)->share($request);

    expect($props['timezone'])->toBe('Europe/Paris')
        ->and($queries)->toBe([]);
});
