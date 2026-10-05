<?php

// Lists every `require` entry whose lower bound rose since <git-ref>.
//
// A consumer that already has such a package locked cannot take the new kit
// with `composer update lvntr/laravel-starter-kit` alone: without `-W` Composer
// keeps the locked version and quietly installs the newest kit that still fits
// (13.8.2 raised lvntr/api-dock to ~0.0.8; apps stayed on 13.8.1). A newly
// added package is not a floor raise — Composer installs it without `-W`.
//
// Usage: php scripts/ci/check-dependency-floors.php <git-ref>
// Prints "name: old -> new" per raised floor. Exit 1 when any, 2 when the ref's
// composer.json cannot be read, 0 otherwise.

require __DIR__.'/../../vendor/autoload.php';

use Composer\Semver\VersionParser;

$root = dirname(__DIR__, 2);
$ref = $argv[1] ?? '';

$old = json_decode((string) shell_exec('git -C '.escapeshellarg($root).' show '.escapeshellarg($ref.':composer.json').' 2>/dev/null'), true)['require'] ?? null;
$new = json_decode((string) file_get_contents($root.'/composer.json'), true)['require'] ?? [];

if (! is_array($old)) {
    fwrite(STDERR, "composer.json not readable at '{$ref}'\n");
    exit(2);
}

$parser = new VersionParser;
$raised = 0;

foreach ($new as $name => $constraint) {
    if (! isset($old[$name])) {
        continue;
    }

    $before = $parser->parseConstraints($old[$name])->getLowerBound();
    $after = $parser->parseConstraints($constraint)->getLowerBound();

    if ($after->compareTo($before, '>')) {
        echo "{$name}: {$old[$name]} -> {$constraint}\n";
        $raised++;
    }
}

exit($raised > 0 ? 1 : 0);
