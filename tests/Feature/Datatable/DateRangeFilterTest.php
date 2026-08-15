<?php

use Illuminate\Foundation\Auth\User as Authenticatable;
use Lvntr\StarterKit\Http\Responses\DatatableQueryBuilder;
use Spatie\QueryBuilder\QueryBuilderRequest;

class DateRangeFilterTestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * @param  array{from?: string, to?: string}  $range
 * @return list<string>
 */
function dateRangeFilterEmails(array $range): array
{
    request()->replace([
        'filter' => array_filter([
            'created_at_from' => $range['from'] ?? null,
            'created_at_to' => $range['to'] ?? null,
        ], fn (?string $value): bool => $value !== null),
    ]);

    $payload = DatatableQueryBuilder::for(DateRangeFilterTestUser::class)
        ->filterable(DatatableQueryBuilder::dateRangeFilters('created_at'))
        ->sortable(['id'])
        ->defaultSort('id')
        ->response()
        ->toResponse(request())
        ->getData(true);

    return array_column($payload['data']['data'], 'email');
}

function createDateRangeFilterUser(string $email, string $createdAt, ?string $timezone = null): DateRangeFilterTestUser
{
    return DateRangeFilterTestUser::query()->create([
        'name' => $email,
        'email' => $email,
        'timezone' => $timezone,
        'password' => 'secret-hash',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

beforeEach(function (): void {
    app()->bind(
        QueryBuilderRequest::class,
        fn (): QueryBuilderRequest => QueryBuilderRequest::fromRequest(request()),
    );

    config()->set('app.timezone', 'UTC');
    config()->set('app.display_timezone', 'UTC');
});

it('uses a positive-offset local day and excludes an instant after that day', function (): void {
    config()->set('app.display_timezone', 'Europe/Istanbul');

    createDateRangeFilterUser('inside@example.com', '2026-01-14 21:30:00');
    createDateRangeFilterUser('after@example.com', '2026-01-15 21:00:01');

    expect(dateRangeFilterEmails(['from' => '2026-01-15', 'to' => '2026-01-15']))
        ->toBe(['inside@example.com']);
});

it('uses a negative-offset local day that extends into the next UTC day', function (): void {
    config()->set('app.display_timezone', 'America/New_York');

    createDateRangeFilterUser('inside@example.com', '2026-01-16 04:30:00');
    createDateRangeFilterUser('after@example.com', '2026-01-16 05:00:00');

    expect(dateRangeFilterEmails(['from' => '2026-01-15', 'to' => '2026-01-15']))
        ->toBe(['inside@example.com']);
});

it('prefers the authenticated user timezone over the site setting', function (): void {
    config()->set('app.display_timezone', 'Europe/Istanbul');

    $viewer = createDateRangeFilterUser('viewer@example.com', '2026-08-15 12:00:00', 'America/New_York');
    createDateRangeFilterUser('new-york-day@example.com', '2026-01-16 04:30:00');

    $this->actingAs($viewer);

    expect(dateRangeFilterEmails(['from' => '2026-01-15', 'to' => '2026-01-15']))
        ->toBe(['new-york-day@example.com']);
});

it('uses the next local midnight across a daylight-saving transition', function (): void {
    config()->set('app.display_timezone', 'America/New_York');

    createDateRangeFilterUser('last-second@example.com', '2026-03-09 03:59:59');
    createDateRangeFilterUser('next-midnight@example.com', '2026-03-09 04:00:00');

    expect(dateRangeFilterEmails(['from' => '2026-03-08', 'to' => '2026-03-08']))
        ->toBe(['last-second@example.com']);
});

it('ignores malformed calendar dates', function (): void {
    createDateRangeFilterUser('kept@example.com', '2026-01-15 12:00:00');

    expect(dateRangeFilterEmails(['from' => 'not-a-date', 'to' => '2026-02-30']))
        ->toBe(['kept@example.com']);
});
