<?php

namespace Lvntr\StarterKit\Domain\Logs\Queries;

use Lvntr\StarterKit\Domain\Logs\DTOs\LogEntryDTO;
use Lvntr\StarterKit\Domain\Logs\DTOs\LogEntryFilterDTO;
use Lvntr\StarterKit\Domain\Logs\Services\LaravelLogParser;
use Lvntr\StarterKit\Exceptions\ApiException;

/**
 * Streams a single Laravel log file, parses entries, applies filters,
 * returns one page worth of LogEntryDTOs plus a byte-offset cursor.
 *
 * Order: newest first. A log file grows at the end, so the page is built
 * by walking BACKWARDS — the window ends at `cursor` (exclusive) and is
 * expanded towards the start of the file in CHUNK_BYTES steps until it
 * holds a full page. The window is then parsed forward (entries can only
 * be parsed in write order) and emitted reversed.
 *
 * Cursor strategy: next_cursor is the byte offset where the OLDEST entry
 * of the current page begins. Re-issuing the request with that cursor
 * returns the page of entries that precede it.
 *
 * Memory safety: a single fopen() + line-by-line fgets() loop over a
 * window capped at MAX_WINDOW_BYTES. Per-line read is capped to
 * PER_LINE_BYTE_CAP to defend against pathological unbounded lines.
 *
 * Reads from storage_path('logs') directly because Laravel writes its
 * log files there at the OS level — outside any registered Flysystem
 * disk (the `local` disk roots at storage/app/, not storage/).
 */
class LogEntryQuery
{
    private const FILENAME_REGEX = '/^[A-Za-z0-9._-]+\.log$/';

    private const PER_LINE_BYTE_CAP = 65536; // 64KB

    private const CHUNK_BYTES = 65536; // 64KB — one backward expansion step

    private const MAX_WINDOW_BYTES = 2097152; // 2MB — scan ceiling for one request

    public function __construct(
        private readonly LaravelLogParser $parser,
    ) {}

    /**
     * @return array{entries: list<array<string, mixed>>, next_cursor: ?int, eof: bool}
     */
    public function paginate(string $filename, LogEntryFilterDTO $filter): array
    {
        $absolutePath = $this->resolveAbsolutePath($filename);

        $size = @filesize($absolutePath);
        if ($size === false) {
            throw ApiException::serverError(__('sk-log.read_failed'));
        }

        $end = max(0, min($filter->cursor ?? $size, $size));
        if ($end === 0) {
            return ['entries' => [], 'next_cursor' => null, 'eof' => true];
        }

        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw ApiException::serverError(__('sk-log.read_failed'));
        }

        try {
            $windowStart = $end;
            /** @var list<array{offset: int, entry: LogEntryDTO}> $parsed */
            $parsed = [];
            /** @var list<array{offset: int, entry: LogEntryDTO}> $matched */
            $matched = [];

            do {
                $windowStart = max(0, $windowStart - self::CHUNK_BYTES);

                // The window is re-parsed from scratch on each expansion.
                // ponytail: quadratic in window size, bounded by MAX_WINDOW_BYTES;
                // build an entry-offset index if that ceiling ever hurts.
                $parsed = $this->parseWindow($handle, $windowStart, $end);
                $matched = array_values(array_filter(
                    $parsed,
                    fn (array $item): bool => $this->matchesFilter($item['entry'], $filter)
                ));
            } while (
                count($matched) < $filter->perPage
                && $windowStart > 0
                && ($end - $windowStart) < self::MAX_WINDOW_BYTES
            );

            // Newest `perPage` matches sit at the tail of the window.
            $page = array_slice($matched, -$filter->perPage);
            $exhausted = $windowStart === 0 && count($page) === count($matched);

            $nextCursor = null;
            if (! $exhausted) {
                $nextCursor = $page !== []
                    ? $page[0]['offset']
                    : ($parsed[0]['offset'] ?? $windowStart);
            }

            return [
                'entries' => array_map(
                    fn (array $item): array => $item['entry']->toArray(),
                    array_reverse($page)
                ),
                'next_cursor' => $nextCursor,
                'eof' => $exhausted,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * Parse one byte window forward, returning each entry with the offset it
     * starts at (ascending). A window that does not start at byte 0 may open
     * mid-entry, so every line before its first header is dropped — that
     * entry belongs to an older page and is emitted there.
     *
     * @param  resource  $handle
     * @return list<array{offset: int, entry: LogEntryDTO}>
     */
    private function parseWindow($handle, int $start, int $end): array
    {
        fseek($handle, $start);

        /** @var list<array{offset: int, entry: LogEntryDTO}> $items */
        $items = [];
        $currentHeader = null;
        $currentOffset = 0;
        /** @var list<string> $currentStack */
        $currentStack = [];
        /** @var list<string> $rawBuffer */
        $rawBuffer = [];
        $rawOffset = 0;

        while (! feof($handle) && ($linePos = ftell($handle)) < $end) {
            $line = fgets($handle, self::PER_LINE_BYTE_CAP);
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");

            $header = $this->parser->parseLine($line);

            if ($header === null) {
                if ($currentHeader !== null) {
                    // Continuation of the current structured entry.
                    $currentStack[] = $line;
                } elseif ($start === 0) {
                    // Pre-header content at the head of the file — buffer so
                    // it surfaces as a raw entry instead of disappearing.
                    if ($rawBuffer === []) {
                        $rawOffset = $linePos;
                    }
                    $rawBuffer[] = $line;
                }

                continue;
            }

            if ($rawBuffer !== []) {
                $items[] = ['offset' => $rawOffset, 'entry' => $this->parser->buildRawEntry($rawBuffer)];
                $rawBuffer = [];
            }
            if ($currentHeader !== null) {
                $items[] = ['offset' => $currentOffset, 'entry' => $this->parser->buildEntry($currentHeader, $currentStack)];
            }

            $currentHeader = $header;
            $currentOffset = $linePos;
            $currentStack = [];
        }

        if ($rawBuffer !== []) {
            $items[] = ['offset' => $rawOffset, 'entry' => $this->parser->buildRawEntry($rawBuffer)];
        }
        if ($currentHeader !== null) {
            $items[] = ['offset' => $currentOffset, 'entry' => $this->parser->buildEntry($currentHeader, $currentStack)];
        }

        return $items;
    }

    private function resolveAbsolutePath(string $filename): string
    {
        if (! preg_match(self::FILENAME_REGEX, $filename)) {
            throw ApiException::badRequest(__('sk-log.invalid_filename'));
        }

        $absolutePath = storage_path('logs/'.$filename);

        if (! file_exists($absolutePath)) {
            throw ApiException::notFound(__('sk-log.file_not_found'));
        }

        return $absolutePath;
    }

    private function matchesFilter(LogEntryDTO $entry, LogEntryFilterDTO $filter): bool
    {
        // Raw entries only show when no structured filters are applied.
        if ($entry->isRaw) {
            return $filter->levels === null
                && $filter->from === null
                && $filter->to === null
                && ($filter->keyword === null || $filter->keyword === '');
        }

        if ($filter->levels !== null && ! in_array($entry->level, $filter->levels, true)) {
            return false;
        }

        if ($filter->from !== null && $entry->timestamp->lt($filter->from)) {
            return false;
        }

        if ($filter->to !== null && $entry->timestamp->gt($filter->to)) {
            return false;
        }

        if ($filter->keyword !== null && $filter->keyword !== '') {
            $haystack = $entry->message.' '.($entry->stack ?? '');
            if (stripos($haystack, $filter->keyword) === false) {
                return false;
            }
        }

        return true;
    }
}
