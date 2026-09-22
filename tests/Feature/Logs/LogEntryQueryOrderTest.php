<?php

/*
|--------------------------------------------------------------------------
| LogEntryQuery — newest-first paging
|--------------------------------------------------------------------------
|
| The viewer reads a growing file, so the page is built by walking the file
| backwards from `cursor` (exclusive) and emitting the window reversed.
| These tests pin the three things that break if that walk is wrong: order,
| no gap/duplicate across cursor pages, and correct stack attachment when a
| page boundary falls between an entry header and its trace.
|
*/

use Lvntr\StarterKit\Domain\Logs\DTOs\LogEntryFilterDTO;
use Lvntr\StarterKit\Domain\Logs\Queries\LogEntryQuery;

function writeLog(string $name, string $contents): string
{
    $dir = storage_path('logs');
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir.'/'.$name, $contents);

    return $name;
}

function drainLog(string $file, int $perPage): array
{
    $query = app(LogEntryQuery::class);
    $messages = [];
    $cursor = null;
    $guard = 0;

    do {
        $page = $query->paginate($file, LogEntryFilterDTO::fromArray([
            'cursor' => $cursor,
            'per_page' => $perPage,
        ]));
        foreach ($page['entries'] as $entry) {
            $messages[] = $entry['message'];
        }
        $cursor = $page['next_cursor'];
        $guard++;
    } while (! $page['eof'] && $guard < 500);

    return $messages;
}

afterEach(function (): void {
    foreach (glob(storage_path('logs').'/sk-order-*.log') ?: [] as $path) {
        @unlink($path);
    }
});

it('returns the newest entry first', function (): void {
    $file = writeLog('sk-order-basic.log', implode("\n", [
        '[2026-09-22 10:00:00] local.INFO: first',
        '[2026-09-22 11:00:00] local.ERROR: second',
        '[2026-09-22 12:00:00] local.WARNING: third',
    ])."\n");

    $page = app(LogEntryQuery::class)->paginate($file, LogEntryFilterDTO::fromArray([]));

    expect(array_column($page['entries'], 'message'))->toBe(['third', 'second', 'first'])
        ->and($page['eof'])->toBeTrue()
        ->and($page['next_cursor'])->toBeNull();
});

it('pages backwards without gaps or duplicates', function (): void {
    $lines = [];
    $expected = [];
    for ($i = 1; $i <= 40; $i++) {
        $lines[] = sprintf('[2026-09-22 10:%02d:00] local.INFO: entry-%d', $i, $i);
        $expected[] = 'entry-'.$i;
    }
    $file = writeLog('sk-order-paged.log', implode("\n", $lines)."\n");

    expect(drainLog($file, 7))->toBe(array_reverse($expected));
});

it('keeps multi-line stacks attached across window expansions', function (): void {
    // Padding pushes the file well past CHUNK_BYTES (64KB) so the backward
    // walk has to expand its window more than once.
    $pad = str_repeat('x', 1024);
    $lines = [];
    $expected = [];
    for ($i = 1; $i <= 120; $i++) {
        $lines[] = sprintf('[2026-09-22 10:00:00] local.ERROR: boom-%d %s', $i, $pad);
        $lines[] = '#0 /app/Foo.php(12): bar()';
        $lines[] = '#1 {main}';
        $expected[] = 'boom-'.$i.' '.$pad;
    }
    $file = writeLog('sk-order-stack.log', implode("\n", $lines)."\n");

    expect(drainLog($file, 10))->toBe(array_reverse($expected));

    $page = app(LogEntryQuery::class)->paginate($file, LogEntryFilterDTO::fromArray(['per_page' => 10]));
    expect($page['entries'][0]['stack'])->toBe("#0 /app/Foo.php(12): bar()\n#1 {main}");
});

it('surfaces pre-header content as a raw entry at the file head', function (): void {
    $file = writeLog('sk-order-raw.log', implode("\n", [
        'PHP Fatal error: something outside the formatter',
        '[2026-09-22 10:00:00] local.INFO: structured',
    ])."\n");

    $page = app(LogEntryQuery::class)->paginate($file, LogEntryFilterDTO::fromArray([]));

    expect(array_column($page['entries'], 'message'))
        ->toBe(['structured', 'PHP Fatal error: something outside the formatter'])
        ->and($page['entries'][1]['is_raw'])->toBeTrue();
});
