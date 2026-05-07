<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor;

/**
 * Her sistem kontrolü bu interface'i implement etmelidir.
 * run() metodu 2 saniye içinde tamamlanmalı; bloke eden işlemler
 * timeout mekanizmasıyla kesilir.
 */
interface DoctorCheck
{
    /** İnsan-okunabilir kontrol adı (tablo satır başlığı). */
    public function name(): string;

    /** Kontrolü çalıştırır ve sonucu döner. */
    public function run(): DoctorReport;
}
