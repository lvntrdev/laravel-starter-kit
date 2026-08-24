<?php

/*
|--------------------------------------------------------------------------
| SecurityHeaders — CSP + storage origin türetme
|--------------------------------------------------------------------------
|
| src/Http/Middleware/SecurityHeaders.php davranışını doğrular:
|
|   1. CSP yalnız non-local ortamda ve response'ta hazır bir CSP yoksa
|      eklenir; local ortam Vite HMR için serbest kalır.
|   2. img-src / media-src / connect-src, media-library disk'i ile public
|      disk'in origin'lerini içerir: disk `url` (CDN), s3 `endpoint`
|      (+ bucket-subdomain wildcard'ı) veya düz AWS region/bucket türevi.
|      Aksi hâlde S3 / Spaces üzerindeki önizleme ve indirmeler tarayıcı
|      tarafından bloklanır.
|   3. `starter-kit.security.csp_extra_origins` yalnız http(s) origin kabul
|      eder; diğer şemalar policy'ye sızamaz.
|
| Test middleware'i doğrudan handle() ile çağırır — route stack kurulmaz.
|
*/

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lvntr\StarterKit\Http\Middleware\SecurityHeaders;

// ──────────────────────────────────────────────────────────────────────────────
// Yardımcılar
// ──────────────────────────────────────────────────────────────────────────────

function securityHeadersResponse(?Response $prepared = null): Response
{
    return (new SecurityHeaders)->handle(
        Request::create('https://app.example.test/admin'),
        static fn (): Response => $prepared ?? new Response('ok'),
    );
}

function securityHeadersCsp(): string
{
    return (string) securityHeadersResponse()->headers->get('Content-Security-Policy');
}

// ──────────────────────────────────────────────────────────────────────────────
// 1. Uygulama koşulları
// ──────────────────────────────────────────────────────────────────────────────

it('applies the baseline CSP outside local', function (): void {
    $csp = securityHeadersCsp();

    expect($csp)->toContain("default-src 'self'")
        ->toContain("img-src 'self' data: blob:")
        ->toContain("media-src 'self' data: blob:")
        ->toContain("object-src 'none'");
});

it('does not apply a CSP in the local environment', function (): void {
    $this->app['env'] = 'local';

    expect(securityHeadersResponse()->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('keeps an already-set CSP untouched', function (): void {
    $prepared = new Response('ok');
    $prepared->headers->set('Content-Security-Policy', "default-src 'none'");

    $csp = securityHeadersResponse($prepared)->headers->get('Content-Security-Policy');

    expect($csp)->toBe("default-src 'none'");
});

// ──────────────────────────────────────────────────────────────────────────────
// 2. Storage origin türetme
// ──────────────────────────────────────────────────────────────────────────────

it('allows the media disk url origin in img, media and connect directives', function (): void {
    config([
        'media-library.disk_name' => 'media',
        'filesystems.disks.media' => [
            'driver' => 's3',
            'url' => 'https://cdn.example.com/files',
        ],
    ]);

    $csp = securityHeadersCsp();

    foreach (explode('; ', $csp) as $directive) {
        if (str_starts_with($directive, 'img-src') || str_starts_with($directive, 'media-src') || str_starts_with($directive, 'connect-src')) {
            expect($directive)->toContain('https://cdn.example.com');
        }
    }

    expect($csp)->toContain('https://cdn.example.com')
        ->not->toContain('https://cdn.example.com/files');
});

it('allows an s3 endpoint origin with a bucket-subdomain wildcard', function (): void {
    config([
        'media-library.disk_name' => 'spaces',
        'filesystems.disks.spaces' => [
            'driver' => 's3',
            'endpoint' => 'https://fra1.digitaloceanspaces.com',
        ],
    ]);

    $csp = securityHeadersCsp();

    expect($csp)->toContain('https://fra1.digitaloceanspaces.com')
        ->toContain('https://*.fra1.digitaloceanspaces.com');
});

it('derives plain AWS origins from region and bucket', function (): void {
    config([
        'media-library.disk_name' => 's3',
        'filesystems.disks.s3' => [
            'driver' => 's3',
            'region' => 'eu-central-1',
            'bucket' => 'my-files',
        ],
    ]);

    $csp = securityHeadersCsp();

    expect($csp)->toContain('https://s3.eu-central-1.amazonaws.com')
        ->toContain('https://my-files.s3.eu-central-1.amazonaws.com');
});

it('includes the public disk url origin', function (): void {
    config(['filesystems.disks.public.url' => 'https://assets.example.com/storage']);

    expect(securityHeadersCsp())->toContain('https://assets.example.com');
});

// ──────────────────────────────────────────────────────────────────────────────
// 3. csp_extra_origins doğrulaması
// ──────────────────────────────────────────────────────────────────────────────

it('appends only valid http(s) extra origins from config', function (): void {
    config([
        'starter-kit.security.csp_extra_origins' => [
            'https://images.example.com',
            'javascript:alert(1)',
            42,
        ],
    ]);

    $csp = securityHeadersCsp();

    expect($csp)->toContain('https://images.example.com')
        ->not->toContain('javascript:');
});
