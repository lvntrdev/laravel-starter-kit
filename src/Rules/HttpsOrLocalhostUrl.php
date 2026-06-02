<?php

namespace Lvntr\StarterKit\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * OAuth redirect_uri whitelist doğrulama kuralı.
 *
 * Kabul edilen URL'ler:
 *   - https:// ile başlayan herhangi bir host (production güvenli)
 *   - http:// ile başlayan localhost, 127.0.0.1, ::1 (yerel geliştirme)
 *
 * Reddedilen URL'ler:
 *   - http:// ile başlayan public host'lar (OAuth phishing riski)
 *   - Geçersiz URL formatları
 *
 * Not: Localhost istisnası yalnızca yerel geliştirme içindir.
 * Production'da redirect_uri olarak http://localhost kabul etmek
 * güvenli kabul edilir (RFC 8252 §8.3).
 */
class HttpsOrLocalhostUrl implements ValidationRule
{
    /**
     * Localhost olarak kabul edilen host'lar (büyük/küçük harf duyarsız).
     *
     * @var string[]
     */
    private const LOCALHOST_HOSTS = ['localhost', '127.0.0.1', '::1'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail(__('validation.url', ['attribute' => $attribute]));

            return;
        }

        $parsed = parse_url($value);

        // Geçerli bir URL olmalı (scheme + host zorunlu)
        if (! isset($parsed['scheme'], $parsed['host'])) {
            $fail(__('validation.url', ['attribute' => $attribute]));

            return;
        }

        $scheme = strtolower($parsed['scheme']);
        $host = strtolower($parsed['host']);

        // https:// — her zaman kabul edilir
        if ($scheme === 'https') {
            return;
        }

        // http:// — yalnızca localhost istisnası ile kabul edilir
        if ($scheme === 'http' && in_array($host, self::LOCALHOST_HOSTS, strict: true)) {
            return;
        }

        $fail(__('sk-api-clients.validation.redirect_uri_https_required'));
    }
}
