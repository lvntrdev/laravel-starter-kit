<?php

/*
|--------------------------------------------------------------------------
| Backward Compatibility — Migration History Immutability
|--------------------------------------------------------------------------
|
| Laravel keys the `migrations` table by basename. If a vendor migration
| ever ships under a different filename than the v13.4.x copy already
| recorded in a consumer database, `migrate` would re-run the file and
| explode with `table already exists`. Renaming or hash-drifting a
| committed vendor migration is therefore strictly forbidden.
|
| This test pins:
|   1. The exact filename list the v13.4.x snapshot brought in.
|   2. The MD5 content hashes recorded in the Task 8 status note — any
|      content drift means the file is no longer the same migration the
|      consumer DB believes it ran.
|
| Both checks fail loud so the regression is caught before release.
|
*/

it('keeps the v13.4.x migration filenames stable', function (): void {
    $migrationDir = dirname(__DIR__, 3).'/database/migrations';

    expect(is_dir($migrationDir))->toBeTrue("Vendor migration directory missing: {$migrationDir}");

    $expected = [
        '2026_04_13_100000_create_global_file_buckets_table.php',
        '2026_04_13_100100_create_file_folders_table.php',
        '2026_05_02_092853_create_file_favorites_table.php',
    ];

    foreach ($expected as $filename) {
        $path = $migrationDir.'/'.$filename;
        expect(is_file($path))->toBeTrue(
            "Migration `{$filename}` is missing in the vendor package — renaming or relocating shipped migrations breaks consumer `migrations` tables."
        );
    }
});

it('keeps the v13.4.x migration content hashes stable', function (): void {
    $migrationDir = dirname(__DIR__, 3).'/database/migrations';

    // Hashes captured during Task 8 — Laravel only checks basename, but
    // hash drift indicates the file's behaviour changed even though the
    // consumer DB already considers it "applied". Any change here must
    // ship as a NEW migration with a fresh timestamp, not an edit.
    $expected = [
        '2026_04_13_100000_create_global_file_buckets_table.php' => 'a8ca3c57f514f3b770b8399295d1fc9d',
        '2026_04_13_100100_create_file_folders_table.php' => 'd0068594843996e829f67d4a55459439',
        '2026_05_02_092853_create_file_favorites_table.php' => '74699db89161caa554f3b79f21db385d',
    ];

    foreach ($expected as $filename => $expectedHash) {
        $path = $migrationDir.'/'.$filename;
        expect(is_file($path))->toBeTrue("Vendor migration `{$filename}` not found.");

        $actual = md5_file($path);
        expect($actual)->toBe(
            $expectedHash,
            "Migration `{$filename}` content drifted (expected {$expectedHash}, got {$actual}). "
            .'Existing user databases already have this filename in their `migrations` table; '
            .'modifying it in place would silently break their schema. Ship a NEW migration instead.'
        );
    }
});

it('does not contain unexpected migration files in the vendor directory', function (): void {
    $migrationDir = dirname(__DIR__, 3).'/database/migrations';

    $expected = [
        '2026_04_13_100000_create_global_file_buckets_table.php',
        '2026_04_13_100100_create_file_folders_table.php',
        '2026_05_02_092853_create_file_favorites_table.php',
        '2026_05_06_100000_create_file_manager_share_revocations_table.php',
    ];

    $actual = collect(scandir($migrationDir))
        ->filter(fn ($file) => str_ends_with($file, '.php'))
        ->values()
        ->all();

    sort($actual);
    sort($expected);

    expect($actual)->toBe(
        $expected,
        'Vendor migration directory contents drifted from the v13.4.x snapshot. '
        .'Adding a NEW migration is fine — extend this fixture in the same PR. '
        .'Removing a migration is breaking and requires a release note.'
    );
});
