<?php

use App\Models\Definition;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\Domain\Shared\Services\DefinitionService;

/*
|--------------------------------------------------------------------------
| DefinitionService — cache invalidation (Task 11 / madde 14)
|--------------------------------------------------------------------------
|
| Regresyon: okuma yolu App::getLocale() ile anahtarlarken, temizleme yolu
| config('app.languages') üzerinde dönüyordu — iki anahtarlama desyncdı ve
| yazma yoluna hiç bağlı değildi. Sonuç: bir definition değişince cache ~1s
| TTL dolana kadar bayat kalıyordu. Fix: tek anahtar + Definition::booted()
| flush.
|
| App\Models\Definition MODEL'i app-owned kalır (publish) ve testbench'te
| autoload edilmediği için doğrudan yüklenir. definitions tablosu beforeEach'te
| inline kurulur (ContentLanguage deseni) — paylaşılan DatabaseTestCase
| şemasına dokunulmaz.
|
*/

$stubs = dirname(__DIR__, 3).'/stubs';

if (! class_exists(Definition::class)) {
    require_once $stubs.'/app/Models/Definition.php';
}

beforeEach(function (): void {
    Schema::create('definitions', function (Blueprint $table): void {
        $table->id();
        $table->string('key')->index();
        $table->string('value');
        $table->string('label');
        $table->text('explanation')->nullable();
        $table->string('severity')->nullable();
        $table->string('icon')->nullable();
        $table->boolean('is_active')->default(true);
        $table->integer('order')->default(0);
        $table->boolean('visibility')->default(true);
        $table->string('lang')->default('en');
        $table->timestamps();
        $table->softDeletes();

        $table->unique(['key', 'value', 'lang']);
    });

    app(DefinitionService::class)->clearCache();
    App::setLocale('en');
});

afterEach(function (): void {
    app(DefinitionService::class)->clearCache();
    Schema::dropIfExists('definitions');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function makeDefinition(array $overrides = []): Definition
{
    return Definition::create($overrides + [
        'key' => 'status',
        'value' => 'active',
        'label' => 'Active',
        'lang' => 'en',
    ]);
}

it('reflects a definition update immediately without waiting for the TTL', function (): void {
    $service = app(DefinitionService::class);
    $definition = makeDefinition();

    // Prime the cache with the original value.
    expect($service->get('status'))->toHaveCount(1)
        ->and($service->get('status')[0]['label'])->toBe('Active');

    // Update through Eloquent — booted() saved() must flush the primed cache.
    $definition->update(['label' => 'Enabled']);

    expect($service->get('status')[0]['label'])->toBe('Enabled');
});

it('drops a soft-deleted definition from the cached read immediately', function (): void {
    $service = app(DefinitionService::class);
    $definition = makeDefinition();

    expect($service->get('status'))->toHaveCount(1);

    $definition->delete();

    expect($service->get('status'))->toBe([]);

    // Restore must bring it back without waiting for the TTL either.
    $definition->restore();

    expect($service->get('status'))->toHaveCount(1);
});

it('flushes every locale from a single write (single-key strategy)', function (): void {
    $service = app(DefinitionService::class);

    makeDefinition(['label' => 'Active', 'lang' => 'en']);
    makeDefinition(['label' => 'Aktif', 'lang' => 'tr']);

    // Prime both locale caches under the one shared key.
    App::setLocale('en');
    expect($service->get('status')[0]['label'])->toBe('Active');
    App::setLocale('tr');
    expect($service->get('status')[0]['label'])->toBe('Aktif');

    // A write while locale is 'tr' must not leave a stale 'en' read behind:
    // the single-key flush invalidates all locales at once. Under the old
    // per-locale + config('app.languages') loop this stayed bayat.
    Definition::query()->where('lang', 'tr')->first()->update(['label' => 'Etkin']);

    App::setLocale('tr');
    expect($service->get('status')[0]['label'])->toBe('Etkin');
    App::setLocale('en');
    expect($service->get('status')[0]['label'])->toBe('Active');
});
