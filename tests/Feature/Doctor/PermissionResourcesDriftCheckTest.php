<?php

/*
|--------------------------------------------------------------------------
| PermissionResourcesDriftCheck Tests
|--------------------------------------------------------------------------
|
| config/permission-resources.php is in UpdateCommand::NEVER_UPDATE_PATHS: the
| updater must never rewrite a project's authorization model. The cost is that a
| resource or ability the kit ADDS (the files.create/update/delete split, for
| one) never reaches an existing installation, and nothing says so — the feature
| just 403s later against a config nobody was told to edit. This check is that
| missing signal, and these tests pin both of its directions: package-shipped
| entries the app lacks are reported; app-only entries never are.
|
*/

use Lvntr\StarterKit\Console\Doctor\Checks\PermissionResourcesDriftCheck;
use Lvntr\StarterKit\StarterKitServiceProvider;

/** Stand-in for the consumer's App\Enums\PermissionEnum (app-owned, absent here). */
enum DriftCheckAbility: string
{
    case Create = 'create';
    case Read = 'read';
    case Update = 'update';
    case Delete = 'delete';
}

/** The matrix exactly as the package ships it. */
function shippedPermissionMatrix(): array
{
    return require StarterKitServiceProvider::stubsPath('config/permission-resources.php');
}

it('passes when the application matrix covers everything the package ships', function (): void {
    config(['permission-resources' => shippedPermissionMatrix()]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isOk())->toBeTrue();
});

it('reports an ability the package added but the application never gained', function (): void {
    $matrix = shippedPermissionMatrix();
    // A pre-split installation: FileManager still has the single write-ish set.
    $matrix['resources']['files'] = ['read', 'create'];

    config(['permission-resources' => $matrix]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('files.update')
        ->and($report->message)->toContain('files.delete')
        ->and($report->hint)->toContain('sk:seed-permissions');
});

it('reports a resource the package ships that the application does not declare', function (): void {
    $matrix = shippedPermissionMatrix();
    unset($matrix['resources']['api-tokens']);

    config(['permission-resources' => $matrix]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('api-tokens');
});

it('never reports a resource the application added on its own', function (): void {
    $matrix = shippedPermissionMatrix();
    $matrix['resources']['students'] = ['create', 'read'];

    config(['permission-resources' => $matrix]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isOk())->toBeTrue()
        ->and($report->message)->not->toContain('students');
});

it('treats a null ability list in the application as covering every ability', function (): void {
    $matrix = shippedPermissionMatrix();
    // null = all abilities; it can never be short of what the package asks for.
    $matrix['resources']['files'] = null;

    config(['permission-resources' => $matrix]);

    expect((new PermissionResourcesDriftCheck)->run()->isOk())->toBeTrue();
});

it('expands a null ability list on the PACKAGE side before comparing', function (): void {
    $matrix = shippedPermissionMatrix();
    // The package ships 'users' => null (all abilities). An application that
    // narrowed it to two is missing the rest — the asymmetry the check exists for.
    $matrix['resources']['users'] = ['read', 'update'];

    config(['permission-resources' => $matrix]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('users.create')
        ->and($report->message)->toContain('users.delete');
});

it('compares enum-valued abilities by their backing value', function (): void {
    $matrix = shippedPermissionMatrix();
    // The config's own docblock offers PermissionEnum cases in place of strings;
    // a matrix written that way must not read as "declares nothing".
    $matrix['resources']['roles'] = [
        DriftCheckAbility::Create,
        DriftCheckAbility::Read,
        DriftCheckAbility::Update,
        DriftCheckAbility::Delete,
    ];

    config(['permission-resources' => $matrix]);

    expect((new PermissionResourcesDriftCheck)->run()->isOk())->toBeTrue();
});

it('ignores an ability entry that is neither a string nor an enum', function (): void {
    $matrix = shippedPermissionMatrix();
    $matrix['resources']['roles'] = [
        new stdClass, // must be skipped, not fatal
        'read',
    ];

    config(['permission-resources' => $matrix]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('roles.create');
});

it('expands a package-side null from the SHIPPED abilities, not the consumer enum', function (): void {
    // The check's PACKAGE_ABILITIES list is what a package-side `null` expands
    // to. It must stay equal to the cases the kit actually ships, and it must
    // NOT be read from App\Enums\PermissionEnum, which the consumer extends:
    // an added `approve` case would otherwise be reported as package drift.
    $source = file_get_contents(StarterKitServiceProvider::stubsPath('app/Enums/PermissionEnum.php'));

    preg_match_all("/case\s+\w+\s*=\s*'([^']+)'/", (string) $source, $matches);
    $shipped = array_map('mb_strtolower', $matches[1]);

    $constant = (new ReflectionClass(PermissionResourcesDriftCheck::class))
        ->getConstant('PACKAGE_ABILITIES');

    expect($shipped)->not->toBeEmpty()
        ->and($constant)->toEqualCanonicalizing($shipped);
});

it('reports a custom permission the package ships that the application lacks', function (): void {
    $matrix = shippedPermissionMatrix();
    $matrix['custom_permissions'] = [];

    config(['permission-resources' => $matrix]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('api-docs.read');
});

it('warns instead of crashing when the application config is absent', function (): void {
    config(['permission-resources' => null]);

    $report = (new PermissionResourcesDriftCheck)->run();

    expect($report->isWarn())->toBeTrue()
        ->and($report->message)->toContain('missing or empty');
});
