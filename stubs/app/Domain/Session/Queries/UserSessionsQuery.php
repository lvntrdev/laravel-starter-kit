<?php

namespace App\Domain\Session\Queries;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Query: Retrieve browser sessions for a given user.
 */
class UserSessionsQuery
{
    /**
     * Get all sessions for the given user.
     *
     * @return array<int, object>
     */
    public function get(int|string $userId, string $currentSessionId): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return collect(
            DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $userId)
                ->orderByDesc('last_activity')
                ->get()
        )->map(function ($session) use ($currentSessionId) {
            $agent = $session->user_agent ?? '';

            return (object) [
                'device' => [
                    'browser' => $this->getBrowser($agent),
                    'platform' => $this->getPlatform($agent),
                    'desktop' => ! $this->isMobile($agent),
                    'mobile' => $this->isMobile($agent),
                ],
                'ip_address' => $session->ip_address,
                'is_current_device' => $session->id === $currentSessionId,
                'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
            ];
        })->toArray();
    }

    protected function getBrowser(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Edg') => 'Edge',
            str_contains($userAgent, 'OPR') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome') && ! str_contains($userAgent, 'Edg') => 'Chrome',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Safari') && ! str_contains($userAgent, 'Chrome') => 'Safari',
            str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident') => 'Internet Explorer',
            default => 'Unknown',
        };
    }

    protected function getPlatform(string $userAgent): string
    {
        return match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') && ! str_contains($userAgent, 'Android') => 'Linux',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            default => 'Unknown',
        };
    }

    protected function isMobile(string $userAgent): bool
    {
        return (bool) preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|Opera Mini|IEMobile/i', $userAgent);
    }
}
