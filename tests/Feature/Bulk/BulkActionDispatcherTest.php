<?php

/*
|--------------------------------------------------------------------------
| BulkActionDispatcher Testleri
|--------------------------------------------------------------------------
|
| Test senaryoları:
|
|   A) BulkActionDispatcher temel davranışı:
|      - register + has + resolve çalışır
|      - Bilinmeyen action key → InvalidArgumentException
|      - dispatch: yetkili item'lar işlenir, yetkisizler atlanır
|      - dispatch: tüm item'lar yetkisizse → processed=0, skipped=N
|      - dispatch: handle() exception atarsa yayılır
|
|   B) BulkDeleteAction (generic) temel davranışı:
|      - getKey() → 'delete'
|      - authorize() varsayılan olarak tüm item'lara izin verir
|      - handle(): başarılı silme → processed artar
|      - handle(): hata veren model → failed listesine eklenir
|
*/

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lvntr\StarterKit\Http\Bulk\BulkAction;
use Lvntr\StarterKit\Http\Bulk\BulkActionDispatcher;
use Lvntr\StarterKit\Http\Bulk\BulkDeleteAction;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcı: sahte BulkAction handler
// ──────────────────────────────────────────────────────────────────────────────

function makeFakeBulkAction(
    string $key,
    ?callable $authorizeFn = null,
    array $handleResult = ['processed' => 0, 'failed' => []],
): BulkAction {
    return new class($key, $authorizeFn, $handleResult) implements BulkAction
    {
        private string $actionKey;

        /** @var callable|null */
        private $authorizeFn;

        private array $result;

        public function __construct(string $k, ?callable $fn, array $result)
        {
            $this->actionKey = $k;
            $this->authorizeFn = $fn;
            $this->result = $result;
        }

        public function getKey(): string
        {
            return $this->actionKey;
        }

        public function authorize(Authenticatable $user, Collection $items): Collection
        {
            if ($this->authorizeFn) {
                return ($this->authorizeFn)($user, $items);
            }

            return $items;
        }

        public function handle(Collection $items): array
        {
            return $this->result;
        }
    };
}

/**
 * Sahte Eloquent model — DB bağlantısı gerektirmez.
 */
function makeFakeModel(int $id): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];
    };
    $model->id = $id;

    return $model;
}

/**
 * Sahte Authenticatable mock (DB gerektirmez).
 */
function makeFakeAuthUser(int $id = 1): Authenticatable
{
    $user = Mockery::mock(Authenticatable::class);
    $user->shouldReceive('getAuthIdentifier')->andReturn($id);

    return $user;
}

// ──────────────────────────────────────────────────────────────────────────────
// A) BulkActionDispatcher
// ──────────────────────────────────────────────────────────────────────────────

it('register ve has doğru çalışır', function (): void {
    $dispatcher = new BulkActionDispatcher;
    $action = makeFakeBulkAction('delete');

    $dispatcher->register($action);

    expect($dispatcher->has('delete'))->toBeTrue()
        ->and($dispatcher->has('archive'))->toBeFalse();
});

it('resolve kayıtlı action\'ı döner', function (): void {
    $dispatcher = new BulkActionDispatcher;
    $action = makeFakeBulkAction('delete');
    $dispatcher->register($action);

    $resolved = $dispatcher->resolve('delete');

    expect($resolved)->toBe($action);
});

it('resolve bilinmeyen key için InvalidArgumentException fırlatır', function (): void {
    $dispatcher = new BulkActionDispatcher;

    expect(fn () => $dispatcher->resolve('nonexistent'))
        ->toThrow(InvalidArgumentException::class);
});

it('dispatch: yetkili item\'lar işlenir, yetkisizler atlanır', function (): void {
    $dispatcher = new BulkActionDispatcher;

    // authorize: sadece çift id'li item'lara izin ver
    $action = makeFakeBulkAction(
        'delete',
        authorizeFn: fn ($user, $items) => $items->filter(fn ($m) => $m->id % 2 === 0)->values(),
        handleResult: ['processed' => 2, 'failed' => []],
    );
    $dispatcher->register($action);

    $user = makeFakeAuthUser();
    $items = new Collection([
        makeFakeModel(1),
        makeFakeModel(2),
        makeFakeModel(3),
        makeFakeModel(4),
    ]);

    $result = $dispatcher->dispatch($user, 'delete', $items);

    expect($result['processed'])->toBe(2)
        ->and($result['skipped'])->toBe(2)
        ->and($result['failed'])->toBeEmpty();
});

it('dispatch: tüm item\'lar yetkisizse processed=0 döner', function (): void {
    $dispatcher = new BulkActionDispatcher;

    $action = makeFakeBulkAction(
        'delete',
        authorizeFn: fn ($user, $items) => new Collection, // hiç izin yok
    );
    $dispatcher->register($action);

    $user = makeFakeAuthUser();
    $items = new Collection([makeFakeModel(1), makeFakeModel(2)]);

    $result = $dispatcher->dispatch($user, 'delete', $items);

    expect($result['processed'])->toBe(0)
        ->and($result['skipped'])->toBe(2)
        ->and($result['failed'])->toBeEmpty();
});

it('dispatch: handle() exception atarsa yayılır', function (): void {
    $dispatcher = new BulkActionDispatcher;

    $throwingAction = new class implements BulkAction
    {
        public function getKey(): string
        {
            return 'delete';
        }

        public function authorize(Authenticatable $user, Collection $items): Collection
        {
            return $items;
        }

        public function handle(Collection $items): array
        {
            throw new RuntimeException('DB constraint violation');
        }
    };

    $dispatcher->register($throwingAction);

    $user = makeFakeAuthUser();
    $items = new Collection([makeFakeModel(1)]);

    expect(fn () => $dispatcher->dispatch($user, 'delete', $items))
        ->toThrow(RuntimeException::class, 'DB constraint violation');
});

it('dispatch: boş items koleksiyonu → processed=0 döner', function (): void {
    $dispatcher = new BulkActionDispatcher;

    $action = makeFakeBulkAction('delete', handleResult: ['processed' => 0, 'failed' => []]);
    $dispatcher->register($action);

    $user = makeFakeAuthUser();
    $items = new Collection; // boş

    $result = $dispatcher->dispatch($user, 'delete', $items);

    expect($result['processed'])->toBe(0)
        ->and($result['skipped'])->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// B) BulkDeleteAction (generic base)
// ──────────────────────────────────────────────────────────────────────────────

it('BulkDeleteAction getKey delete döner', function (): void {
    $action = new BulkDeleteAction;

    expect($action->getKey())->toBe('delete');
});

it('BulkDeleteAction authorize varsayılan tüm item\'lara izin verir', function (): void {
    $action = new BulkDeleteAction;
    $user = makeFakeAuthUser();

    $items = new Collection([
        makeFakeModel(1),
        makeFakeModel(2),
        makeFakeModel(3),
    ]);

    $authorized = $action->authorize($user, $items);

    expect($authorized->count())->toBe(3);
});

it('BulkDeleteAction handle başarılı silme sonrası processed sayar', function (): void {
    $action = new BulkDeleteAction;

    // delete() çağrısında true dönen mock model
    $m1 = Mockery::mock(Model::class);
    $m1->shouldReceive('delete')->once()->andReturn(true);
    $m1->shouldReceive('getKey')->andReturn(1);

    $m2 = Mockery::mock(Model::class);
    $m2->shouldReceive('delete')->once()->andReturn(true);
    $m2->shouldReceive('getKey')->andReturn(2);

    $items = new Collection([$m1, $m2]);

    $result = $action->handle($items);

    expect($result['processed'])->toBe(2)
        ->and($result['failed'])->toBeEmpty();
});

it('BulkDeleteAction handle exception atan model failed listesine eklenir', function (): void {
    $action = new BulkDeleteAction;

    $good = Mockery::mock(Model::class);
    $good->shouldReceive('delete')->once()->andReturn(true);
    $good->shouldReceive('getKey')->andReturn(1);

    $bad = Mockery::mock(Model::class);
    $bad->shouldReceive('delete')->once()->andThrow(new Exception('constraint error'));
    $bad->shouldReceive('getKey')->andReturn(2);

    $items = new Collection([$good, $bad]);

    $result = $action->handle($items);

    expect($result['processed'])->toBe(1)
        ->and($result['failed'])->toHaveCount(1)
        ->and($result['failed'][0]['id'])->toBe(2)
        ->and($result['failed'][0]['reason'])->toContain('constraint error');
});

it('BulkDeleteAction handle tek hata varlığı diğer item\'ları durdurmaz', function (): void {
    $action = new BulkDeleteAction;

    $models = [];
    for ($i = 1; $i <= 5; $i++) {
        $m = Mockery::mock(Model::class);
        if ($i === 3) {
            // 3. item hata atar
            $m->shouldReceive('delete')->andThrow(new Exception("item {$i} failed"));
        } else {
            $m->shouldReceive('delete')->andReturn(true);
        }
        $m->shouldReceive('getKey')->andReturn($i);
        $models[] = $m;
    }

    $items = new Collection($models);
    $result = $action->handle($items);

    expect($result['processed'])->toBe(4)
        ->and($result['failed'])->toHaveCount(1);
});
