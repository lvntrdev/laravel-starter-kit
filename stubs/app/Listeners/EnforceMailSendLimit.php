<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Enforces the optional hourly outgoing-mail cap from the mail settings.
 *
 * The limit is read from `config('mail.send_limit')`, which SettingsServiceProvider
 * populates from the persisted mail settings each request (0 = unlimited). When the
 * rolling hourly count reaches the cap, returning false from a MessageSending listener
 * cancels the send — Laravel will not dispatch the blocked message.
 */
class EnforceMailSendLimit
{
    public function handle(MessageSending $event): bool
    {
        $limit = (int) config('mail.send_limit', 0);

        if ($limit <= 0) {
            return true; // No cap configured.
        }

        $key = 'sk:mail:send_count:'.now()->format('Y-m-d-H');

        // Ensure the counter exists with a 1-hour TTL before incrementing so the
        // window expires on its own (atomic where the cache store supports it).
        Cache::add($key, 0, now()->addHour());
        $count = (int) Cache::increment($key);

        if ($count > $limit) {
            Cache::decrement($key);
            Log::warning('Outgoing mail blocked: hourly send limit reached.', [
                'limit' => $limit,
            ]);

            return false; // Cancels the send.
        }

        return true;
    }
}
