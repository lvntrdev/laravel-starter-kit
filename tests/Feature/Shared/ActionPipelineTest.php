<?php

use Illuminate\Support\Facades\DB;
use Lvntr\StarterKit\Domain\Shared\Contracts\PipeableAction;
use Lvntr\StarterKit\Domain\Shared\Pipelines\ActionPipeline;

/*
|--------------------------------------------------------------------------
| ActionPipeline — transaction/rollback davranışı
|--------------------------------------------------------------------------
|
| ActionPipeline::run(), varsayılan olarak DB::transaction() ile sarmalanır
| (src/Domain/Shared/Pipelines/ActionPipeline.php:91). Bu test doğrular:
|   1. Başarılı zincirde tüm pipe'ların yazımı commit edilir.
|   2. Ortada exception fırlatan pipe varsa önceki pipe'ın yazımı da
|      rollback edilir (transaction sarmalı sayesinde).
|   3. withoutTransaction() kullanıldığında exception durumunda önceki
|      pipe'ın yazımı KALICI kalır — rollback OLMAZ.
|
| settings tablosu (DatabaseTestCase::defineDatabaseMigrations()) test
| yazımları için kullanılır; ayrı bir şema kurmaya gerek yok.
|
*/

/**
 * Test pipe: settings tablosuna bir satır ekler, payload'ı olduğu gibi geçirir.
 */
final class PipelineTestSettingWriter implements PipeableAction
{
    public function __construct(private string $key) {}

    public function handle(mixed $payload, Closure $next): mixed
    {
        DB::table('settings')->insert([
            'group' => 'pipeline_test',
            'key' => $this->key,
            'value' => 'written',
            'encrypted' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $next($payload);
    }
}

/**
 * Test pipe: zincirin ortasında hata simülasyonu için her zaman exception fırlatır.
 */
final class PipelineTestExceptionThrower implements PipeableAction
{
    public function handle(mixed $payload, Closure $next): mixed
    {
        throw new RuntimeException('pipeline mid-chain failure');
    }
}

it('commits all pipe writes when the chain succeeds', function (): void {
    $result = ActionPipeline::make()
        ->send(['ok' => true])
        ->through([
            new PipelineTestSettingWriter('first'),
            new PipelineTestSettingWriter('second'),
        ])
        ->run();

    expect($result)->toBe(['ok' => true]);
    expect(DB::table('settings')->where('group', 'pipeline_test')->count())->toBe(2);
});

it('rolls back previous pipe writes when a later pipe throws (default transaction wrapping)', function (): void {
    expect(fn () => ActionPipeline::make()
        ->send(['ok' => true])
        ->through([
            new PipelineTestSettingWriter('before-failure'),
            new PipelineTestExceptionThrower,
        ])
        ->run())->toThrow(RuntimeException::class, 'pipeline mid-chain failure');

    // Transaction sarmalandığı için ilk pipe'ın yazımı da geri alınmalı.
    expect(DB::table('settings')->where('group', 'pipeline_test')->count())->toBe(0);
});

it('does NOT roll back previous pipe writes when withoutTransaction() is used', function (): void {
    expect(fn () => ActionPipeline::make()
        ->send(['ok' => true])
        ->withoutTransaction()
        ->through([
            new PipelineTestSettingWriter('before-failure-no-tx'),
            new PipelineTestExceptionThrower,
        ])
        ->run())->toThrow(RuntimeException::class, 'pipeline mid-chain failure');

    // Transaction sarmalanmadığından ilk pipe'ın yazımı KALICIDIR.
    expect(DB::table('settings')->where('group', 'pipeline_test')->count())->toBe(1);
    expect(DB::table('settings')->where('key', 'before-failure-no-tx')->exists())->toBeTrue();
});
