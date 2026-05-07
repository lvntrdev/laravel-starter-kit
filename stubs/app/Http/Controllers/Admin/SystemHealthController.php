<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin panel sistem sağlık kontrolü ekranı.
 *
 * sk:doctor komutunu HTTP üzerinden tetikler ve JSON çıktısını
 * Inertia sayfasına prop olarak iletir.
 *
 * Thin per project convention:
 *   - Auth gate → 'system.health.view' permission
 *   - Business logic → sk:doctor Artisan command (Lvntr\StarterKit)
 */
class SystemHealthController extends Controller
{
    /**
     * Sistem sağlık ekranını göster.
     *
     * Yalnızca boş state ile sayfa render edilir (report: null).
     * sk:doctor çalıştırmaz — ilk açılışta UI donmasını önler.
     * Frontend onMounted içinde run() POST endpoint'ini çağırarak sonuçları çeker.
     */
    public function index(): Response
    {
        Gate::authorize('system.health.view');

        return Inertia::render('Admin/SystemHealth/Index', [
            'report' => session('doctor_report'),
        ]);
    }

    /**
     * sk:doctor komutunu yeniden çalıştır ve sayfayı güncelle.
     *
     * Inertia router.post ile çağrılır; back() ile çağrıldığı sayfaya
     * (Settings tab'ı veya bağımsız sayfa) dönülür. Doctor raporu flash
     * üzerinden iletilir; ilgili index() çağrıları onu prop'a aktarır.
     */
    public function run(): RedirectResponse
    {
        Gate::authorize('system.health.view');

        $report = $this->runDoctorCommand();

        $summary = $report['summary'] ?? [];
        $failCount = $summary['fail'] ?? 0;
        $warnCount = $summary['warn'] ?? 0;

        session()->flash('doctor_report', $report);

        if ($failCount > 0) {
            return redirect()->route('admin.system-health.index')->with('error', __('sk-system-health.run_fail', ['count' => $failCount]));
        }

        if ($warnCount > 0) {
            return redirect()->route('admin.system-health.index')->with('warning', __('sk-system-health.run_warn', ['count' => $warnCount]));
        }

        return redirect()->route('admin.system-health.index')->with('success', __('sk-system-health.run_ok'));
    }

    /**
     * sk:doctor --json çalıştır ve çıktıyı dizi olarak döndür.
     *
     * Artisan::call HTTP request içinde bloklayıcıdır; check'lerin 2 saniyelik
     * timeout disiplini DoctorCommand tarafından uygulanır (T1 kapsamı).
     *
     * @return array<string, mixed>
     */
    private function runDoctorCommand(): array
    {
        Artisan::call('sk:doctor', ['--json' => true]);
        $raw = Artisan::output();

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [
                'version' => 1,
                'generated_at' => now()->toISOString(),
                'summary' => ['ok' => 0, 'warn' => 0, 'fail' => 0, 'total' => 0],
                'checks' => [],
            ];
        }

        return $decoded;
    }
}
