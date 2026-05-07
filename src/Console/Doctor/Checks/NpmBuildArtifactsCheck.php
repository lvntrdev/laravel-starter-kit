<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

/**
 * Vite build artifact'lerinin varlığını kontrol eder.
 * public/build/manifest.json yoksa frontend derlemesi yapılmamış demektir.
 */
class NpmBuildArtifactsCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'NPM Build Artifacts';
    }

    public function run(): DoctorReport
    {
        $manifestPath = function_exists('public_path')
            ? public_path('build/manifest.json')
            : base_path('public/build/manifest.json');

        if (! file_exists($manifestPath)) {
            $env = config('app.env', app()->environment());

            if ($env === 'production') {
                return DoctorReport::fail(
                    $this->name(),
                    'public/build/manifest.json not found — frontend build is missing.',
                    'Run npm run build.'
                );
            }

            return DoctorReport::warn(
                $this->name(),
                'public/build/manifest.json not found.',
                'Run npm run build or npm run dev.'
            );
        }

        // Manifest geçerli JSON mi?
        $content = file_get_contents($manifestPath);
        $decoded = json_decode($content ?: '', true);

        if (! is_array($decoded) || $decoded === []) {
            return DoctorReport::fail(
                $this->name(),
                'public/build/manifest.json is invalid or empty.',
                'Run npm run build again.'
            );
        }

        $assetCount = count($decoded);

        return DoctorReport::ok(
            $this->name(),
            "NPM build artifacts present ({$assetCount} assets)."
        );
    }
}
