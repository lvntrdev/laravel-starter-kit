<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;
use Lvntr\StarterKit\Support\KitDependencies;

/**
 * Kit'in `composer.json` `require` bloğunda listelenen ama consumer app'te
 * kurulu olmayan paketleri tespit eder. Tespit mantığı `KitDependencies`'te
 * tekilleştirilmiştir; burada yalnızca `DoctorReport`'a çevrilir.
 *
 * Eksik paket yoksa, consumer'ın kök composer.json'ının kit'in yönettiği bir
 * `lvntr/*` paketini doğrudan isteyip istemediğine bakar (WARN): o kayıt
 * sonraki kit sürümünü sessizce engelleyebilir.
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
