<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Node.js runtime sürümünü kontrol eder. Floor, frontend toolchain'in gerçek
 * motor gereksinimidir: stubs Vite 7 kullanır → `node ^20.19.0 || >=22.12.0`.
 * Salt-major "18+" kontrolü yanıltıcı OK verirdi (Node 18 / 20.18 geçer ama
 * `npm run build` sonradan patlar). `node` PATH'te yoksa ya da sürüm parse
 * edilemezse warn üretir — Node yalnızca asset derlemesi için gerekli
 * olduğundan runtime'ı bloke etmez.
 */
class NodeVersionCheck implements DoctorCheck
{
    // Vite 7 engine floor: "^20.19.0 || >=22.12.0" (Node 21 dahil değil).
    private const MIN_LABEL = '20.19+ / 22.12+';

    /**
     * Vite 7'nin "^20.19.0 || >=22.12.0" semver aralığını birebir uygular.
     */
    private static function meetsFloor(int $major, int $minor): bool
    {
        return ($major === 20 && $minor >= 19)
            || ($major === 22 && $minor >= 12)
            || $major > 22;
    }

    public function name(): string
    {
        return 'Node Version';
    }

    public function run(): DoctorReport
    {
        try {
            $process = new Process(['node', '-v'], null, null, null, 5.0);
            $process->run();
        } catch (Throwable $e) {
            return DoctorReport::warn(
                $this->name(),
                'Could not execute "node -v": '.$e->getMessage(),
                'Install Node.js '.self::MIN_LABEL.' to build frontend assets.'
            );
        }

        if (! $process->isSuccessful()) {
            return DoctorReport::warn(
                $this->name(),
                'Node.js is not installed or not available in PATH.',
                'Install Node.js '.self::MIN_LABEL.' — the frontend build (vite/npm) requires it.'
            );
        }

        $raw = trim($process->getOutput());

        // Beklenen format: v18.17.0
        if (! preg_match('/v?(\d+)\.(\d+)\.(\d+)/', $raw, $m)) {
            return DoctorReport::warn(
                $this->name(),
                "Could not parse Node.js version from output: \"{$raw}\".",
                'Verify your Node.js installation with node -v.'
            );
        }

        $major = (int) $m[1];
        $minor = (int) $m[2];
        $version = "v{$m[1]}.{$m[2]}.{$m[3]}";

        if (! self::meetsFloor($major, $minor)) {
            return DoctorReport::warn(
                $this->name(),
                "Node.js {$version} is below the frontend toolchain floor (Vite 7 needs Node ".self::MIN_LABEL.').',
                'Upgrade Node.js to '.self::MIN_LABEL.' (e.g. via nvm) before building assets.'
            );
        }

        return DoctorReport::ok(
            $this->name(),
            "Node.js {$version} meets the frontend toolchain minimum requirement (Node ".self::MIN_LABEL.').'
        );
    }
}
