<?php

/*
|--------------------------------------------------------------------------
| HtmlSanitizer — markup emitted by the extended editor toolbar
|--------------------------------------------------------------------------
|
| The editor gained sub/sup, typography styles (font-size / font-family /
| line-height) and a YouTube node. Each has to survive a save, and each
| widening of the allowlist has to stay narrow: the YouTube iframe is the
| only iframe that lives, and only with the attributes an embed needs.
|
*/

use Lvntr\StarterKit\Support\HtmlSanitizer;

it('keeps sub and sup', function (): void {
    expect(HtmlSanitizer::clean('<p>H<sub>2</sub>O x<sup>2</sup></p>'))
        ->toBe('<p>H<sub>2</sub>O x<sup>2</sup></p>');
});

it('keeps typography declarations on a span', function (): void {
    $clean = HtmlSanitizer::clean(
        '<p><span style="font-size: 18px; font-family: Georgia, serif; line-height: 1.5">x</span></p>',
    );

    expect($clean)->toContain('font-size: 18px')
        ->toContain('font-family: Georgia, serif')
        ->toContain('line-height: 1.5');
});

it('drops typography values that are not plain lengths or names', function (string $style): void {
    expect(HtmlSanitizer::clean('<p><span style="'.$style.'">x</span></p>'))->toBe('<p><span>x</span></p>');
})->with([
    'font-size expression' => ['font-size: calc(100vw)'],
    'font-family url' => ['font-family: url(https://evil.test/f)'],
    'font-family escape' => ['font-family: a\\3b color'],
    'line-height keyword' => ['line-height: inherit'],
]);

it('keeps a youtube-nocookie embed with only its safe attributes', function (): void {
    $clean = HtmlSanitizer::clean(
        '<div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=1" '
        .'width="640" height="360" allowfullscreen="true" srcdoc="<script>alert(1)</script>" '
        .'onload="alert(1)" autoplay="false"></iframe></div>',
    );

    expect($clean)->toContain('data-youtube-video')
        ->toContain('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=1"')
        ->toContain('width="640"')
        ->toContain('allowfullscreen')
        ->not->toContain('srcdoc')
        ->not->toContain('onload')
        ->not->toContain('autoplay');
});

it('drops any other iframe', function (string $src): void {
    expect(HtmlSanitizer::clean('<div><iframe src="'.$src.'"></iframe></div>'))->not->toContain('iframe');
})->with([
    'other host' => ['https://evil.test/embed/dQw4w9WgXcQ'],
    'look-alike host' => ['https://www.youtube-nocookie.com.evil.test/embed/dQw4w9WgXcQ'],
    'http' => ['http://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'javascript' => ['javascript:alert(1)'],
    'no src' => [''],
]);
