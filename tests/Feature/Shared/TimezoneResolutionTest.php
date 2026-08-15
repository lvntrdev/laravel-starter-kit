<?php

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;

it('resolves the display timezone in user, site, app, UTC order without leaking between users', function (): void {
    config([
        'app.display_timezone' => 'Europe/Berlin',
        'app.timezone' => 'America/New_York',
    ]);

    expect(resolve_display_timezone((object) ['id' => 101, 'timezone' => 'Europe/Istanbul']))
        ->toBe('Europe/Istanbul')
        ->and(resolve_display_timezone((object) ['id' => 102, 'timezone' => null]))
        ->toBe('Europe/Berlin');

    config(['app.display_timezone' => null]);

    expect(resolve_display_timezone((object) ['id' => 103, 'timezone' => null]))
        ->toBe('America/New_York');

    config(['app.timezone' => null]);

    expect(resolve_display_timezone((object) ['id' => 104, 'timezone' => null]))
        ->toBe('UTC');
});

it('resolves the authenticated user timezone when no user is passed', function (): void {
    config([
        'app.display_timezone' => 'Europe/Berlin',
        'app.timezone' => 'UTC',
    ]);

    $this->actingAs(new GenericUser([
        'id' => 201,
        'timezone' => 'Asia/Tokyo',
    ]));

    expect(resolve_display_timezone())->toBe('Asia/Tokyo');
});

it('falls through an invalid stored timezone instead of throwing', function (): void {
    config([
        'app.display_timezone' => 'Europe/London',
        'app.timezone' => 'UTC',
    ]);

    $user = (object) ['id' => 301, 'timezone' => 'Invalid/Stale_Timezone'];

    expect(resolve_display_timezone($user))->toBe('Europe/London');
});

it('falls back to the site timezone without an authenticated user', function (): void {
    app('auth')->forgetGuards();
    config([
        'app.display_timezone' => 'Europe/Paris',
        'app.timezone' => 'UTC',
    ]);

    expect(auth()->user())->toBeNull()
        ->and(resolve_display_timezone())->toBe('Europe/Paris');
});

it('memoizes the resolved timezone per request and user id', function (): void {
    $user = (object) ['id' => 401, 'timezone' => 'Europe/Istanbul'];

    expect(resolve_display_timezone($user))->toBe('Europe/Istanbul');

    $user->timezone = 'Asia/Tokyo';

    expect(resolve_display_timezone($user))->toBe('Europe/Istanbul')
        ->and(resolve_display_timezone((object) ['id' => 402, 'timezone' => 'Asia/Tokyo']))
        ->toBe('Asia/Tokyo');
});

it('keeps the existing format_date output in every mode for two-argument callers', function (): void {
    config([
        'app.display_timezone' => 'Europe/Istanbul',
        'app.timezone' => 'UTC',
        'app.date_format' => 'd-m-Y',
    ]);

    $value = Carbon::create(2026, 3, 14, 5, 36, 0, 'UTC');

    expect(format_date($value))->toBe('14-03-2026 08:36')
        ->and(format_date($value, 'date'))->toBe('14-03-2026')
        ->and(format_date($value, 'time'))->toBe('08:36');
});

it('honours the explicit format_date timezone override', function (): void {
    config([
        'app.display_timezone' => 'Europe/Istanbul',
        'app.timezone' => 'UTC',
        'app.date_format' => 'd-m-Y',
    ]);

    $value = Carbon::create(2026, 3, 14, 5, 36, 0, 'UTC');

    expect(format_date($value, 'datetime', 'America/New_York'))
        ->toBe('14-03-2026 01:36');
});

it('returns a parseable ISO-8601 API date carrying the resolved offset', function (): void {
    config([
        'app.display_timezone' => 'Europe/Istanbul',
        'app.timezone' => 'UTC',
    ]);

    $formatted = to_api_date('2026-03-14 05:36:00 UTC');
    $parsed = Carbon::parse($formatted);

    expect($formatted)->toBe('2026-03-14T08:36:00+03:00')
        ->and($parsed->getOffset())->toBe(10_800);
});

it('returns null for null date helper inputs and empty API input', function (): void {
    expect(format_date(null))->toBeNull()
        ->and(to_api_date(null))->toBeNull()
        ->and(to_api_date(''))->toBeNull();
});
