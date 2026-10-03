> On-demand reference for `lvntr-kit-domain` — exact PHP API. Read when you need a signature.

Runtime classes live in `Lvntr\StarterKit\…` (vendor). Most also resolve as `App\…` via `class_alias`
(an app copy always wins); where an alias does NOT exist this file says so. Do not edit vendor.

---

## Base classes & pipeline

| Class (FQCN you type) | Also as | Contract |
|---|---|---|
| `Lvntr\StarterKit\Domain\Shared\Actions\BaseAction` | `App\Domain\Shared\Actions\BaseAction` | `abstract class`, **empty marker**. One public `execute()` by convention; not enforced. |
| `Lvntr\StarterKit\Domain\Shared\DTOs\BaseDTO` | `App\Domain\Shared\DTOs\BaseDTO` | `abstract readonly class`; `abstract public static fromArray(array $data): static`. No `toArray()` in the base. |
| `Lvntr\StarterKit\Domain\Shared\Contracts\PipeableAction` | `App\Domain\Shared\Contracts\PipeableAction` | `handle(mixed $payload, \Closure $next): mixed` |
| `Lvntr\StarterKit\Domain\Shared\Pipelines\ActionPipeline` | `App\Domain\Shared\Pipelines\ActionPipeline` | see below |
| `Lvntr\StarterKit\Domain\Shared\Services\DefinitionService` | `App\Domain\Shared\Services\DefinitionService` | see Definitions |

`ActionPipeline` (wraps `Illuminate\Pipeline\Pipeline`; pipes resolved from the container):

- `ActionPipeline::make(): static`
- `send(mixed $passable): static` — initial payload
- `through(array $pipes): static` — class-strings or instances, run in order
- `withoutTransaction(): static` — opt out of the default `DB::transaction`
- `run(): mixed` — returns what the last pipe returns (`thenReturn()`); any throw rolls the transaction back

```php
class ReserveStockAction implements PipeableAction
{
    public function handle(mixed $payload, \Closure $next): mixed
    {
        // mutate/validate $payload, then continue the chain
        return $next($payload);
    }
}

$order = ActionPipeline::make()
    ->send($dto)
    ->through([ReserveStockAction::class, CreateOrderAction::class, SendReceiptAction::class])
    ->run(); // one DB::transaction around all three
```

Use it only for multi-step transactional workflows; single-action flows call `->execute()` directly.

---

## ApiResponse

`Lvntr\StarterKit\Http\Responses\ApiResponse` (alias `App\Http\Responses\ApiResponse`, registered on every boot).
Implements `Responsable` — return it straight from a controller. Constructor is `protected`; **do not subclass**.

**Static factories**

| Call | Status | Default message | Notes |
|---|---|---|---|
| `success(mixed $data = null, string $message = 'Operation successful.'): static` | 200 | | |
| `created(mixed $data = null, string $message = 'Record created.'): static` | 201 | | |
| `error(string $message = 'An error occurred.', int $status = 400): static` | `$status` | | `success=false`, `data=null` |
| `noContent(): JsonResponse` | 204 | | Raw `JsonResponse('')`, **not** an `ApiResponse` (no fluent chain) |
| `paginated(LengthAwarePaginator\|CursorPaginator\|Paginator $p, string $message = 'Operation successful.'): static` | 200 | | `data = $p->items()`; pagination goes to `meta` |
| `paginatedCollection(ResourceCollection $c, string $message = 'Operation successful.'): static` | 200 | | Keeps Resource transformation; `$c->resource` must be a paginator |

Pagination `meta` keys — length-aware: `current_page, from, last_page, path, per_page, to, total`;
cursor: `path, per_page, next_cursor, prev_cursor, has_more`; simple: `current_page, path, per_page, has_more`.

**Fluent instance methods** (all return `static`)

- `message(string)` · `status(int)` · `errors(?array)` — sets/clears the `errors` key
- `meta(array)` — **merges** into existing meta
- `header(string $key, string $value)` · `headers(array)` — extra response headers (merge)
- `traceId(string)` · `debug(array)` — normally set by middleware/handler, rarely by hand
- `toArray(): array` — envelope payload · `toResponse($request): JsonResponse`

Envelope keys: `success, status, message, data` always; `errors` only when non-null; `meta` only when non-empty;
`trace_id` when known (auto-filled from `AssignTraceId`); `debug` only when set. `toResponse` also sets
`X-Request-ID` (= trace id) and `X-Correlation-ID` when the middleware recorded one.

---

## ApiException + handler mapping

`Lvntr\StarterKit\Exceptions\ApiException` (alias `App\Exceptions\ApiException`) extends Symfony `HttpException`.
`new ApiException(int $statusCode = 400, string $message = 'An error occurred.', ?\Throwable $previous = null)`.
Message is a plain string — it carries **no field errors**.

| Factory | Status | Default message |
|---|---|---|
| `badRequest(string $message = 'Bad request.')` | 400 | Bad request. |
| `unauthorized(string $message = 'Authentication required.')` | 401 | Authentication required. |
| `forbidden(string $message = 'You are not authorized for this action.')` | 403 | You are not authorized for this action. |
| `notFound(string $message = 'Record not found.')` | 404 | Record not found. |
| `methodNotAllowed(string $message = 'HTTP method not allowed.')` | 405 | HTTP method not allowed. |
| `conflict(string $message = 'Record already exists.')` | 409 | Record already exists. |
| `unprocessable(string $message = 'Unprocessable entity.')` | 422 | Unprocessable entity. |
| `tooManyRequests(string $message = 'Too many requests. Please wait.')` | 429 | Too many requests. Please wait. |
| `serverError(string $message = 'A server error occurred.')` | 500 | A server error occurred. |

`Lvntr\StarterKit\Exceptions\DomainRuleException extends \LogicException` — **no `App\` alias**; import the vendor FQCN.
Throw it from Actions for business-guard violations (`throw new DomainRuleException('Folder already exists.')`).
It is the only `LogicException` the handler maps (to 422); any other `LogicException` stays a 500.

**`ApiExceptionHandler`** (alias `App\Exceptions\ApiExceptionHandler`, wired through `Bootstrap::exceptions()`):
renders the envelope only when `$request->is('api/*')` or `expectsJson()`. First matching row wins:

| Exception | Status | Message |
|---|---|---|
| `ApiException` | its own | its own |
| `DomainRuleException` | 422 | its own |
| `ModelNotFoundException` | 404 | `"{ModelBasename} not found."` (`The requested resource was not found.` if model unknown) |
| `NotFoundHttpException` | 404 | model message when previous is `ModelNotFoundException`, else `Endpoint not found.` |
| `ValidationException` | 422 | `Validation error.` + `errors` = `$e->errors()` |
| `MethodNotAllowedHttpException` | 405 | This HTTP method is not allowed for this endpoint. |
| `AuthenticationException` | 401 | Authentication required. |
| `AuthorizationException` | 403 | You are not authorized for this action. |
| `ThrottleRequestsException` | 429 | `Too many requests. Please try again after N seconds.` (no `Retry-After` → `…try again later.`); upstream rate-limit headers copied |
| other `HttpExceptionInterface` (`abort()`) | its code | generic text per status (raw message dropped) |
| Spatie `FileIsTooBig` | 422 | The uploaded file is too large. |
| Spatie `FileNameNotAllowed` | 422 | The uploaded file name is not allowed. |
| anything else | 500 | A server error occurred. (raw message never leaked) |

Side effects: status >= 500 (non-validation) is logged with `trace_id`; `APP_DEBUG=true` adds a `debug` block
(exception, file:line, 5-frame trace); a client `X-Request-ID` matching `[A-Za-z0-9._-]{1,128}` is echoed as
`X-Correlation-ID` (the response `X-Request-ID` is always the server trace id).
Field-level 422 from your own code: `throw ValidationException::withMessages([...])`, or
`ApiResponse::error($msg, 422)->errors([...])`.

---

## DatatableQueryBuilder

`Lvntr\StarterKit\Http\Responses\DatatableQueryBuilder`. In an app import the shim
`App\Http\Responses\DatatableQueryBuilder` (a thin subclass you may extend). Wraps Spatie QueryBuilder; constructor is private.

| Method | Meaning / default |
|---|---|
| `static for(string\|Builder $subject): self` | Model class-string **or** Eloquent builder (use a builder for scopes/`withCount`) |
| `searchable(array $fields): static` | Enables `filter[search]`. Input split on whitespace; **each word must LIKE-match any field** (OR across fields, AND across words); `%`/`_` escaped |
| `sortable(array $fields): static` | Allowed `?sort=name` / `?sort=-name`. Items: column `string` or `Spatie\QueryBuilder\AllowedSort` |
| `filterable(array $fields): static` | Allowed `filter[x]`. `string` → `AllowedFilter::exact()`; pass an `AllowedFilter` for callbacks/scopes |
| `columns(array $columns): static` | Column menu meta. Entries: `'key'` or `['key'=>…, 'label'?, 'sortable'?, 'visible'?, 'locked'?]`. Enables `?columns=a,b` row shaping and adds `columns` to the payload |
| `alwaysInclude(array $keys): static` | Row keys kept regardless of `?columns=`. Default `['id']` (`id` is always added) |
| `defaultSort(string $field): static` | Default `'-created_at'`; prefix `-` = desc |
| `with(array $relations): static` | Eager-load relations |
| `resource(string $resourceClass): static` | Map each row through a `JsonResource` (`::collection(...)->resolve()`) |
| `perPage(int $perPage): static` | Default page size: `config('starter-kit.datatable.default_per_page')`, else 10 |
| `response(): ApiResponse` | Runs the query, returns `ApiResponse::success($payload)` |
| `static dateRangeFilters(string $column): list<AllowedFilter>` | Two callback filters `{col}_from` / `{col}_to`; spread into `filterable([...])` |
| `static applyCalendarDateRange(Builder $q, string $column, mixed $from = null, mixed $to = null): void` | Inclusive local-calendar `Y-m-d` bounds in the user's display timezone (`from` >= local start of day, `to` < next local day); invalid/non-`Y-m-d` values ignored |
| `static applySearchWords(Builder $q, array $fields, string\|bool\|array $value): void` | The search predicate itself; accepts the bool/array shapes Spatie hands the callback (`true`→"1", `false`→nothing, arrays re-joined with `,`) |

Request params: `page`, `per_page` (clamped to `1..config('starter-kit.datatable.max_per_page')`, default max 100),
`sort`, `filter[search]`, `filter[<key>]`, `columns`. Shaping is fail-closed: once `columns` is present, rows are cut to
`alwaysInclude` + valid requested keys (dot keys keep their first segment), never the full row.

`response()` envelope `data` (what `<SkDatatable>` consumes):

```json
{ "data": [/* rows */], "total": 42, "per_page": 10, "current_page": 1,
  "last_page": 5, "from": 1, "to": 10, "columns": [/* only if columns() was set */] }
```

Always call `applySearchWords()` / `applyCalendarDateRange()` for bulk "select all filtered" queries so the table and
bulk sets cannot drift. Translatable JSON columns: `Lvntr\StarterKit\Support\TranslatableQueryHelpers`
(alias `App\Support\TranslatableQueryHelpers`) — `searchFilter(string $column, ?string $locale = null): AllowedFilter`,
`localeSort(string $column, ?string $locale = null): AllowedSort`, `resourceShape(Model $m, string $attr): array`
(`['translations' => [...], 'current' => '…']`).

---

## Helpers

Global functions from `src/sk-helpers.php` (loaded by the service provider; each is `function_exists`-guarded, so a copy in
`app/Helpers/sk-helpers.php` wins).

| Helper | Returns | Behaviour |
|---|---|---|
| `to_api(mixed $data = null, string $message = 'Operation successful.', int $status = 200)` | `ApiResponse\|JsonResponse` | `status >= 400` → `ApiResponse::error($message, $status)` (**data ignored**); `204` → `ApiResponse::noContent()` (raw `JsonResponse`); `LengthAware/Cursor/Simple` paginator → `paginated()` with meta; `AnonymousResourceCollection` over a paginator → `paginatedCollection()`; `201` → `created()`; else `success()` (other 2xx get `->status($status)`). Named-arg form: `to_api(status: 204)` |
| `format_date(Carbon\|string\|null $value, string $type = 'datetime', ?string $timezone = null): ?string` | string/null | `null`→`null`. Converts to the display timezone; `$type` = `'date'` (config `app.date_format`, default `d-m-Y`), `'time'` (`H:i`), else `<date_format> H:i` |
| `to_api_date(Carbon\|string\|null $value): ?string` | ISO-8601 | e.g. `2026-03-14T08:36:00+03:00` in the display timezone; `null`/`''` → `null`. Use when the client formats the date itself |
| `resolve_display_timezone(?object $user = null): string` | IANA id | user `timezone` → `app.display_timezone` → `app.timezone` → `UTC`; invalid ids skipped; memoised per request |
| `format_money(int\|float\|string\|null $amount, ?string $currency = null): ?string` | string/null | `null`/`''` → `null`; currency defaults to `app.currency` (`TRY`); symbols `₺ $ € £`, else `"CODE "`; format `number_format(x, 2, ',', '.')` |
| `definition(string $key, mixed $value): ?object` | object/null | Item (`value,label,order,severity,icon,explanation,visibility`) for the current locale; compares values as strings |
| `definitionLabel(string $key, mixed $value): ?string` | string/null | `definition(...)?->label` |
| `sk_locale_keys(): list<string>` | | `array_keys(config('app.languages'))` |
| `sk_default_locale(): string` | | `app.default_locale` if still active, else first active locale, else `app.fallback_locale` |

---

## Definitions

DB-backed label/value lookups (table `definitions`, model `App\Models\Definition` — app-owned, soft-deletes, flushes the
cache on any write). Unique on `(key, value, lang)`; `lang` is capped at 35 chars. Seeded via seeder/migration (no admin CRUD).

`DefinitionService` (`App\Domain\Shared\Services\DefinitionService`; resolve with `app(DefinitionService::class)` or DI):

- `get(string $key): list<array>` — items for the **current locale**, `[]` if none. Each item: `value, label, order, severity, icon, explanation, visibility`. Only `is_active = true`, ordered by `order`.
- `all(?array $keys = null): array<string, list<array>>` — grouped by key for the current locale; `$keys` filters.
- `clearCache(): void` — forgets `DefinitionService::CACHE_KEY` (`'definitions'`). One entry holds every locale, TTL 3600s.
- Call `clearCache()` after bulk upserts that bypass model events (seeders).

HTTP: `GET /api/v1/definitions?keys=a,b` (API) and `GET /definitions?keys=a,b` (web service route); frontend: `useDefinition()`.

```php
definitionLabel('userStatus', $user->status);     // 'Active' / 'Aktif' by locale
definition('userStatus', 'active')?->severity;    // 'success'
app(DefinitionService::class)->all(['userStatus']);
```

---

## Bulk-action recipe

Mirrors `Users` (`POST admin/users/bulk`, route `users.bulk`). Pieces:

| Piece | Class | Notes |
|---|---|---|
| Contract | `Lvntr\StarterKit\Http\Bulk\BulkAction` (shim `App\Http\Contracts\BulkAction`) | `getKey(): string` · `authorize(Authenticatable $user, Collection $items): Collection` · `handle(Collection $items): array{processed:int, failed:list<array{id,reason}>}` |
| Base delete | `Lvntr\StarterKit\Http\Bulk\BulkDeleteAction` (app shim `App\Http\BulkActions\BulkDeleteAction` — **extend this**) | key `'delete'`; `authorize()` allows all (override!); `handle()` per-item `$model->delete()` with try/catch into `failed` |
| Dispatcher | `Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher` (shim `App\Http\Actions\BulkActionDispatcher`) | `register(BulkAction)`, `has(string)`, `resolve(string)` (throws `InvalidArgumentException`), `dispatch(Authenticatable $user, string $key, Collection $items): array{processed, skipped, failed, message}` |
| Request | `App\Http\Requests\Admin\BulkActionRequest` (app-owned stub) | `action` (string, max 64); `ids` (array, max 500, required unless `select_all_filtered`); `select_all_filtered` (bool); `filter_snapshot` (nullable array) |
| Snapshot | `Lvntr\StarterKit\Support\BulkFilterSnapshot::normalize(array $snapshot, array $allowed): array` | Flattens `filter[x]` / nested `filter`; **fail-closed**: an active filter outside `$allowed` (or non-scalar) → 422 `sk-bulk.unknown_filters`; `'true'`/`'false'` → bool; non-`filter` keys ignored |

`dispatch()` authorizes per item **outside** the transaction (`skipped` = requested − authorized), then runs `handle()` inside `DB::transaction`.

1. **Action** — `app/Domain/Product/BulkActions/BulkDeleteProductAction.php`: `extends App\Http\BulkActions\BulkDeleteAction`, override `authorize()` to return only rows the actor may delete (permission via `$user->can('products.delete')` + ownership/rank rules, self-guards). Skipped rows are not errors.
2. **Selection query** (only for cross-page "select all filtered") — `ProductBulkSelectionQuery::resolve(User $actor, array $snapshot): Collection`: start from the **same visibility scope** as the datatable, `BulkFilterSnapshot::normalize($snapshot, ['status','search','created_at_from','created_at_to'])`, apply with the same predicates (`DatatableQueryBuilder::applySearchWords()` / `::applyCalendarDateRange()`), `orderBy('id')->limit(MAX_ITEMS)` (Users: `const MAX_ITEMS = 5000`). Reference: `UserBulkSelectionQuery`.
3. **Route** — in `routes/web/product-route.php`: `Route::post('bulk', 'bulk')->name('bulk');` inside the `products.` group, declared **before** the `Route::resource`. The route name `products.bulk` derives no permission, so it is "unresolved": allowed with a warn-log by default. Declare it under `config('starter-kit.permissions.unrestricted_routes')` (e.g. `'products.bulk'`) to say it is deliberate; the per-item `authorize()` is the real gate.
4. **Controller** (Admin, returns a redirect with flash):

```php
public function bulk(BulkActionRequest $request, BulkDeleteProductAction $bulkDelete, ProductBulkSelectionQuery $selection): RedirectResponse
{
    $dispatcher = new BulkActionDispatcher;            // Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher
    $dispatcher->register($bulkDelete);
    $key = $request->validated('action');
    if (! $dispatcher->has($key)) {
        return back()->with('error', __('sk-bulk.unsupported_action', ['action' => $key]));
    }
    $actor = $request->user();
    $items = $request->boolean('select_all_filtered')
        ? $selection->resolve($actor, $request->validated('filter_snapshot') ?? [])
        : Product::query()->whereIn('id', $request->validated('ids'))->get();
    $result = $dispatcher->dispatch($actor, $key, $items);

    return back()->with('success', $result['message']);   // warn via ->with('warning', …) if the cap was hit
}
```

5. **Frontend** posts through `useDatatableSelection()` (see `lvntr-kit-frontend`). Add a `sk-bulk` translation only if you add messages beyond the shipped `sk-bulk.*` keys.

---

## Useful traits

| Trait | FQCN (no `App\` alias — traits can't be aliased) | API |
|---|---|---|
| `HasMediaCollections` | `Lvntr\StarterKit\Traits\HasMediaCollections` | Includes Spatie `InteractsWithMedia`. `syncMediaCollection(string $collection, array $items): void` — `UploadedFile` = add; int/string = media id to keep; every other media in the collection is deleted. `getMediaForForm(string $collection): array` → `list<{id,name,url,size,mime_type}>` (temporary URL 30 min, falls back to `getUrl()`) |
| `HasActivityLogging` | `Lvntr\StarterKit\Traits\HasActivityLogging` | Includes Spatie `LogsActivity`. `getActivitylogOptions()` logs only dirty fillable (or unguarded) attributes, skips empty diffs, and **never logs credentials** (`SENSITIVE_LOG_ATTRIBUTES` + credential-suffix names, case-insensitive). Extend with `protected function sensitiveLogAttributes(): array { return [...static::SENSITIVE_LOG_ATTRIBUTES, 'iban']; }`. If you override `getActivitylogOptions()` keep the `logExcept()` call or the leak re-opens |
| `HasTranslatableRules` | `Lvntr\StarterKit\Support\HasTranslatableRules` (FormRequest) | `translatableRules(string $attribute, array $rules, array $options = []): array` → keys `attr.{locale}`; default locale gets `$rules`, others get them with `required`→`nullable`; options `primary`, `optional`, `only`, `except`. `translatableAttributes(array $attributes): array` → labels `"Label (Lang)"` per locale. Spread into `rules()` / `attributes()` |

Models using them in the kit: `User` (activity + media), `Role`/`Permission` (activity), `GlobalFileBucket` (media).
