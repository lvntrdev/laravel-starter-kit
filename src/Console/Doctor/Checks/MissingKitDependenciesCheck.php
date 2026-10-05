<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Support\KitDependencies;
use Lvntr\StarterKit\Support\KitVersion;

/**
 * Kit'in `composer.json` `require` bloğunda listelenen ama consumer app'te
 * kurulu olmayan paketleri tespit eder. Tespit mantığı `KitDependencies`'te
 * tekilleştirilmiştir; burada yalnızca `DoctorReport`'a çevrilir.
 *
 * Eksik paket yoksa, consumer'ın kök composer.json'ının kit'in yönettiği bir
 * `lvntr/*` paketini doğrudan isteyip istemediğine bakar (WARN): o kayıt
 * sonraki kit sürümünü sessizce engelleyebilir.
 *
 * Sonra Packagist'e sorar: aynı major hatta daha yeni bir kit sürümü varken
 * uygulama eskisinde kaldıysa (genelde `-W`'siz `composer update`) WARN.
 */
class MissingKitDependenciesCheck implements DoctorCheck
{
    public function name(): string
    {
        return (string) __('sk-doctor.missing_kit_dependencies.name');
    }

    public function run(): DoctorReport
    {
        $missing = KitDependencies::missing();

        $pinned = $missing === [] ? KitDependencies::rootPinned() : [];

        if ($pinned !== []) {
            return DoctorReport::warn(
                $this->name(),
                (string) __('sk-doctor.missing_kit_dependencies.root_pinned', ['packages' => implode(', ', $pinned)]),
                (string) __('sk-doctor.missing_kit_dependencies.root_pinned_hint', ['packages' => implode(' ', $pinned)])
            );
        }

        $heldBackBy = $missing === [] ? KitDependencies::heldBackBy() : null;

        if ($heldBackBy !== null) {
            return DoctorReport::warn(
                $this->name(),
                (string) __('sk-doctor.missing_kit_dependencies.held_back', ['installed' => (string) KitVersion::tag(), 'latest' => $heldBackBy]),
                (string) __('sk-doctor.missing_kit_dependencies.held_back_hint', ['version' => ltrim($heldBackBy, 'v')])
            );
        }

        if ($missing === []) {
            return DoctorReport::ok(
                $this->name(),
                (string) __('sk-doctor.missing_kit_dependencies.all_installed')
            );
        }

        return DoctorReport::fail(
            $this->name(),
            (string) __('sk-doctor.missing_kit_dependencies.missing', ['packages' => implode(', ', $missing)]),
            (string) __('sk-doctor.missing_kit_dependencies.missing_hint')
        );
    }
}
