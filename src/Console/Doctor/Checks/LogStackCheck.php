<?php

declare(strict_types=1);

namespace Lvntr\StarterKit\Console\Doctor\Checks;

use Lvntr\StarterKit\Console\Doctor\DoctorCheck;
use Lvntr\StarterKit\Console\Doctor\DoctorReport;

class LogStackCheck implements DoctorCheck
{
    public function name(): string
    {
        return 'Log Stack';
    }

    public function run(): DoctorReport
    {
        // config(), not env(): once the configuration is cached, .env is not
        // loaded and env() would report the default instead of the effective
        // runtime setting.
        $default = trim((string) config('logging.default', 'stack'));

        // Only the ACTIVE channel matters. Reading logging.channels.stack
        // unconditionally warned about a stack that LOG_CHANNEL=daily never
        // reaches — a false positive on a correctly configured app.
        $channels = $this->resolveActiveChannels($default);

        $unrotated = array_values(array_filter(
            $channels,
            static fn (string $channel): bool => config("logging.channels.{$channel}.driver") === 'single'
        ));

        if ($unrotated !== []) {
            return DoctorReport::warn(
                $this->name(),
                sprintf(
                    'Active log channel "%s" writes through an unrotated "single" driver (%s) — logs grow unbounded.',
                    $default,
                    implode(', ', $unrotated)
                ),
                $this->remediation($default)
            );
        }

        return DoctorReport::ok(
            $this->name(),
            sprintf(
                'Active log channel "%s" (%s) — no unrotated "single" driver in use.',
                $default,
                implode(', ', $channels) ?: 'no channel resolved'
            )
        );
    }

    /**
     * Expand a channel into the concrete channels a log record actually
     * reaches.
     *
     * Mirrors `LogManager::createStackDriver()`: a `channels` value written as
     * a string is exploded on commas, and every member is resolved through the
     * manager again — so a stack MAY nest another stack, and the previous
     * single-level expansion reported a nested `single` as rotated. `$seen`
     * carries the current resolution PATH, so a configuration cycle (a stack
     * that lists itself, directly or through a member) terminates instead of
     * recursing until the stack overflows; siblings still resolve independently.
     *
     * @param  array<string, true>  $seen
     * @return list<string>
     */
    private function resolveActiveChannels(string $channel, array $seen = []): array
    {
        if ($channel === '' || isset($seen[$channel])) {
            return [];
        }

        if (! $this->isStack($channel)) {
            return [$channel];
        }

        $seen[$channel] = true;

        $members = config("logging.channels.{$channel}.channels", ['single']);

        if (is_string($members)) {
            $members = explode(',', $members);
        }

        $resolved = [];

        foreach ((array) $members as $member) {
            foreach ($this->resolveActiveChannels(trim((string) $member), $seen) as $leaf) {
                $resolved[] = $leaf;
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * The remediation line that actually reaches the offending channel.
     *
     * `LOG_STACK` is wired to `logging.channels.stack.channels` and nothing
     * else, so it only helps when the ACTIVE channel is the framework's own
     * `stack`. An app whose LOG_CHANNEL points at a differently named stack
     * would follow a `LOG_STACK=daily` hint, change nothing, and keep writing
     * through the unrotated driver — so that case is sent to its own config
     * key instead.
     */
    private function remediation(string $channel): string
    {
        if ($channel === 'stack') {
            return 'Set LOG_STACK=daily in .env to enable automatic log rotation.';
        }

        if ($this->isStack($channel)) {
            return sprintf(
                'Replace the "single" member of logging.channels.%s.channels with "daily" '
                .'(LOG_STACK only configures the framework\'s own "stack" channel).',
                $channel
            );
        }

        return 'Set LOG_CHANNEL=daily in .env to enable automatic log rotation.';
    }

    private function isStack(string $channel): bool
    {
        return config("logging.channels.{$channel}.driver") === 'stack';
    }
}
