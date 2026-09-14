<?php

/*
|--------------------------------------------------------------------------
| HtmlSanitizer — control-character normalization before the scheme test
|--------------------------------------------------------------------------
|
| isSafeUrl() classifies a value as "relative" (safe) the moment it fails to
| match `scheme:`. A browser ignores ASCII TAB/LF/CR (and other C0 control
| characters) inside a URL before it ever looks at the scheme, so
| `java\tscript:alert(1)` reads as a relative URL to the old regex but as
| `javascript:alert(1)` to the browser that renders the saved href — the
| dangerous scheme survives sanitization by hiding inside whitespace the
| check didn't know to strip.
|
| The entity cases matter on their own: clean() loads through
| DOMDocument::loadHTML, which DECODES character references, so an attacker
| never has to type a literal control byte — `&#9;` / `&#x9;` / `&Tab;`
| reach isSafeUrl() as a real TAB. They pin that the normalization happens
| AFTER parsing, which is the only place it can work.
|
| This file pins: every control-character-smuggled `javascript:` is stripped
| from href AND src, ordinary safe schemes and relative URLs are untouched,
| and a whitespace-padded safe URL still survives (proving the normalization
| is control-character-specific, not a behavior change to trimming).
|
*/

use Lvntr\StarterKit\Support\HtmlSanitizer;

/**
 * Sanitize a single `<a href="...">` and return the surviving href, or null
 * when the attribute was stripped.
 */
function sanitizerHref(string $rawHref): ?string
{
    $html = '<a href="'.$rawHref.'">link</a>';
    $clean = HtmlSanitizer::clean($html);

    return preg_match('/href="([^"]*)"/', $clean, $m) === 1 ? html_entity_decode($m[1]) : null;
}

/**
 * Same for `<img src="...">` — filterAttributes() routes href and src through
 * the identical check, so the matrix has to hold for both.
 */
function sanitizerSrc(string $rawSrc): ?string
{
    $html = '<img src="'.$rawSrc.'" alt="x">';
    $clean = HtmlSanitizer::clean($html);

    return preg_match('/src="([^"]*)"/', $clean, $m) === 1 ? html_entity_decode($m[1]) : null;
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Control-character-smuggled javascript: is stripped in every case
// ──────────────────────────────────────────────────────────────────────────────

it('strips javascript: hrefs with an embedded control character', function (string $label, string $rawHref): void {
    expect(sanitizerHref($rawHref))->toBeNull();
})->with([
    'TAB' => ['tab', "java\tscript:alert(1)"],
    'LF' => ['lf', "java\nscript:alert(1)"],
    'CR' => ['cr', "java\rscript:alert(1)"],
    'other C0 (0x01)' => ['c0', "java\x01script:alert(1)"],
]);

it('strips javascript: hrefs written as a numeric character reference', function (string $label, string $rawHref): void {
    expect(sanitizerHref($rawHref))->toBeNull();
})->with([
    'decimal TAB' => ['dec-tab', 'java&#9;script:alert(1)'],
    'hex TAB' => ['hex-tab', 'java&#x9;script:alert(1)'],
    'decimal LF' => ['dec-lf', 'java&#10;script:alert(1)'],
    'decimal CR' => ['dec-cr', 'java&#13;script:alert(1)'],
]);

/**
 * The HTML5 named entities are asserted on the OUTCOME, not on removal: only
 * libxml 2.10+ decodes `&Tab;` / `&NewLine;`, and the package requires no such
 * floor. On a newer parser they become a real control character and the href is
 * dropped; on libxml 2.9.x they stay undecoded and the href survives with the
 * ampersand serialized as `&amp;`, which a browser reads as the literal text
 * `java&Tab;script:` — not a scheme. Both are safe. What must never happen on
 * either parser is a value that still resolves to `javascript:` once the
 * browser drops the control characters, which is what this collapses and pins.
 */
it('never leaves a live javascript: scheme when a named entity carries the control character', function (string $label, string $rawHref): void {
    $href = sanitizerHref($rawHref);
    $collapsed = $href === null ? '' : strtolower(preg_replace('/[\x00-\x1F]/', '', $href) ?? '');

    expect($collapsed)->not->toStartWith('javascript:');
})->with([
    'named TAB' => ['named-tab', 'java&Tab;script:alert(1)'],
    'named LF' => ['named-lf', 'java&NewLine;script:alert(1)'],
]);

it('strips a plain javascript: href with no control characters', function (): void {
    expect(sanitizerHref('javascript:alert(1)'))->toBeNull();
});

it('strips a javascript: href padded with leading and trailing control characters', function (): void {
    expect(sanitizerHref("\t javascript:alert(1) \n"))->toBeNull();
});

it('strips other non-allowlisted schemes hidden behind a control character', function (string $label, string $rawHref): void {
    expect(sanitizerHref($rawHref))->toBeNull();
})->with([
    'vbscript' => ['vbscript', "vb\tscript:msgbox(1)"],
    'data' => ['data', "da\nta:text/html;base64,PHNjcmlwdD4="],
]);

// ──────────────────────────────────────────────────────────────────────────────
// 2. Safe schemes and relative URLs survive untouched
// ──────────────────────────────────────────────────────────────────────────────

it('keeps ordinary safe schemes and relative URLs', function (string $url): void {
    expect(sanitizerHref($url))->toBe($url);
})->with([
    'http' => ['http://example.test/page'],
    'https' => ['https://example.test/page'],
    'mailto' => ['mailto:person@example.test'],
    'tel' => ['tel:+15551234567'],
    'root-relative' => ['/media/x.jpg'],
    'fragment' => ['#top'],
    'query-only' => ['?q=1'],
    'dot-relative' => ['./about'],
]);

// ──────────────────────────────────────────────────────────────────────────────
// 3. Whitespace-padded safe URL still survives (normalized, not rejected)
// ──────────────────────────────────────────────────────────────────────────────

it('keeps a leading/trailing-whitespace https URL, normalized', function (): void {
    expect(sanitizerHref('  https://example.test/page  '))->toBe('https://example.test/page');
});

// ──────────────────────────────────────────────────────────────────────────────
// 4. img src runs the same matrix — filterAttributes handles href and src together
// ──────────────────────────────────────────────────────────────────────────────

it('strips javascript: srcs with an embedded control character', function (string $label, string $rawSrc): void {
    expect(sanitizerSrc($rawSrc))->toBeNull();
})->with([
    'TAB' => ['tab', "java\tscript:alert(1)"],
    'LF' => ['lf', "java\nscript:alert(1)"],
    'CR' => ['cr', "java\rscript:alert(1)"],
    'decimal TAB entity' => ['dec-tab', 'java&#9;script:alert(1)'],
    'plain' => ['plain', 'javascript:alert(1)'],
]);

it('keeps safe and relative img srcs', function (string $url): void {
    expect(sanitizerSrc($url))->toBe($url);
})->with([
    'https' => ['https://example.test/a.png'],
    'root-relative' => ['/media/x.jpg'],
    'dot-relative' => ['./a.png'],
]);
