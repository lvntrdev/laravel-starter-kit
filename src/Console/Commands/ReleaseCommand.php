<?php

namespace Lvntr\StarterKit\Console\Commands;

use Illuminate\Console\Command;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class ReleaseCommand extends Command
{
    protected $signature = 'sk:release';

    protected $description = 'Starter Kit paketini etiketleyip Git\'e gonder (release tag / CHANGELOG icin)';

    private string $packagePath;

    public function handle(): int
    {
        $this->packagePath = StarterKitServiceProvider::basePath();

        // Gelistirici ortami dogrulamasi: vendor/ altinda .git olmaz
        if (! is_dir($this->packagePath.'/.git')) {
            $this->components->error('sk:release yalnizca paket kaynak dizininde calistirilabilir.');
            $this->line('  Paket vendor/ altinda yuklu gorunuyor — bu komut paket gelistiricisine ozeldir.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info('Starter Kit Yayin');
        $this->newLine();

        // 1. Remote kontrolu
        if (! $this->hasRemote()) {
            $this->components->error('Paket icin git remote yapilandirilmamis.');
            $this->line('  Calistir: cd '.escapeshellarg($this->packagePath));
            $this->line('  Sonra: git remote add origin <repo-url>');

            return self::FAILURE;
        }

        // 2. Calisma dizini temiz mi kontrol et — release SADECE tag atar, commit YAPMAZ
        if (! $this->isClean()) {
            $this->components->error('Pakette commit edilmemis degisiklikler var:');
            $this->newLine();
            $this->line($this->git('status', '--short'));
            $this->newLine();
            $this->line('  Once degisiklikleri commit et, sonra release yap.');
            $this->newLine();

            return self::FAILURE;
        }

        // 3. Mevcut versiyonu goster
        $currentTag = $this->getLatestTag();
        if ($currentTag) {
            $this->components->twoColumnDetail('Mevcut versiyon', "<fg=cyan>{$currentTag}</>");
        } else {
            $this->components->twoColumnDetail('Mevcut versiyon', '<fg=yellow>henuz etiket yok</>');
        }

        // 4. Yeni versiyon sor
        $bumpType = select(
            label: 'Versiyon artirma tipi',
            options: [
                'patch' => "Patch (hata duzeltme) → {$this->bumpVersion($currentTag, 'patch')}",
                'minor' => "Minor (yeni ozellik) → {$this->bumpVersion($currentTag, 'minor')}",
                'major' => "Major (kiran degisiklik) → {$this->bumpVersion($currentTag, 'major')}",
                'custom' => 'Ozel versiyon',
            ],
            default: 'patch',
        );

        if ($bumpType === 'custom') {
            $version = text('Versiyon (orn. 1.0.0):', required: true);
        } else {
            $version = $this->bumpVersion($currentTag, $bumpType);
        }

        if (! str_starts_with($version, 'v')) {
            $version = 'v'.$version;
        }

        // 5. Etiket zaten var mi kontrol et
        $existingTags = $this->git('tag', '-l', $version);
        if (trim($existingTags) === $version) {
            $this->components->error("{$version} etiketi zaten mevcut.");

            return self::FAILURE;
        }

        $cleanVersion = $this->stripV($version);

        // 6. Onayla
        $this->newLine();
        $this->components->twoColumnDetail('Yeni versiyon', "<fg=green>{$version}</>");
        $remote = trim($this->git('remote', 'get-url', 'origin'));
        $this->components->twoColumnDetail('Remote', $remote);
        $this->newLine();

        if (! confirm("{$version} yayinlansin mi?", true)) {
            $this->components->warn('Yayin iptal edildi.');

            return self::SUCCESS;
        }

        // 7. Etiket olustur — annotation body CHANGELOG.md'den cekiliyor
        $changelogBody = $this->extractChangelogSection($cleanVersion);
        $tagArgs = ['tag', '-a', $version, '-m', "Release {$version}"];
        if ($changelogBody !== null) {
            $tagArgs[] = '-m';
            $tagArgs[] = $changelogBody;
            $this->components->twoColumnDetail('Tag annotation', '<fg=green>CHANGELOG\'dan dolduruldu</>');
        } else {
            $this->components->twoColumnDetail('Tag annotation', '<fg=yellow>CHANGELOG\'da '.$cleanVersion.' bulunamadi</>');
        }
        $this->git(...$tagArgs);
        $this->components->twoColumnDetail("{$version} etiketi", '<fg=green>OLUSTURULDU</>');

        // 8. Commit'leri ve etiketleri gonder
        $currentBranch = trim($this->git('rev-parse', '--abbrev-ref', 'HEAD'));
        $this->line('  <fg=gray>→</> Remote\'a gonderiliyor ('.$currentBranch.')...');
        $this->git('push', 'origin', $currentBranch, '--tags');
        $this->components->twoColumnDetail('Push', '<fg=green>TAMAM</>');

        // 9. Ozet
        $this->newLine();
        $this->components->info("{$version} basariyla yayinlandi!");
        $this->newLine();
        $this->line('  Paketi yuklemek icin:');
        $this->line("  <fg=cyan>composer require lvntr/laravel-starter-kit:\"^{$cleanVersion}\"</>");
        $this->newLine();

        return self::SUCCESS;
    }

    private function git(string ...$args): string
    {
        $process = new Process(['git', ...$args], $this->packagePath);
        $process->run();

        return $process->getOutput();
    }

    private function hasRemote(): bool
    {
        return trim($this->git('remote')) !== '';
    }

    private function isClean(): bool
    {
        return trim($this->git('status', '--porcelain')) === '';
    }

    private function getLatestTag(): ?string
    {
        $tag = trim($this->git('describe', '--tags', '--abbrev=0'));

        return $tag !== '' ? $tag : null;
    }

    /** @return array{int, int, int} */
    private function parseVersion(?string $tag): array
    {
        if (! $tag) {
            return [0, 0, 0];
        }

        $parts = explode('.', ltrim($tag, 'v'));

        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
            (int) ($parts[2] ?? 0),
        ];
    }

    private function bumpVersion(?string $currentTag, string $type): string
    {
        [$major, $minor, $patch] = $this->parseVersion($currentTag);

        return match ($type) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    private function stripV(string $version): string
    {
        return ltrim($version, 'v');
    }

    /**
     * Paketin CHANGELOG.md dosyasindan "## [X.Y.Z]" ile baslayan bolumu cek.
     * Keep a Changelog formatinda yazilmis olmasi beklenir.
     */
    private function extractChangelogSection(string $cleanVersion): ?string
    {
        $path = $this->packagePath.'/CHANGELOG.md';
        if (! is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);
        $pattern = '/^##\s*\[?'.preg_quote($cleanVersion, '/').'\]?[^\n]*\n(.+?)(?=^##\s|\z)/sm';

        if (preg_match($pattern, $contents, $matches) !== 1) {
            return null;
        }

        $body = trim($matches[1]);
        $body = preg_replace('/\n---\s*$/', '', $body) ?? $body;

        return trim($body);
    }
}
