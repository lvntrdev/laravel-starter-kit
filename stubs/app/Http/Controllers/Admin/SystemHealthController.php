<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

class SystemHealthController extends Controller
{
    /**
     * sk:doctor komutunu çalıştır.
     *
     * JSON isteğinde (Settings tab'ı) raporu doğrudan döner — sayfa navigasyonu olmaz,
     * tab state korunur. Normal istekte Settings sayfasına redirect yapar.
     */
    public function run(): ApiResponse|RedirectResponse
    {
        Gate::authorize('system.health.view');

        $report = $this->runDoctorCommand();

        $summary = $report['summary'] ?? [];
        $failCount = $summary['fail'] ?? 0;
        $warnCount = $summary['warn'] ?? 0;

        if (request()->wantsJson()) {
            $type = match (true) {
                $failCount > 0 => 'error',
                $warnCount > 0 => 'warning',
                default => 'success',
            };

            $message = match ($type) {
                'error' => __('sk-system-health.run_fail', ['count' => $failCount]),
                'warning' => __('sk-system-health.run_warn', ['count' => $warnCount]),
                default => __('sk-system-health.run_ok'),
            };

            return to_api(['report' => $report, 'type' => $type, 'message' => $message], $message);
        }

        session()->flash('doctor_report', $report);

        if ($failCount > 0) {
            return redirect()->route('settings.index')->with('error', __('sk-system-health.run_fail', ['count' => $failCount]));
        }

        if ($warnCount > 0) {
            return redirect()->route('settings.index')->with('warning', __('sk-system-health.run_warn', ['count' => $warnCount]));
        }

        return redirect()->route('settings.index')->with('success', __('sk-system-health.run_ok'));
    }

    /**
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
