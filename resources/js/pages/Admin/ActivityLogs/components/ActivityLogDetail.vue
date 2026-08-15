<script setup lang="ts">
    import { formatDateTime } from '@lvntr/components/utils/datetime';

    interface ActivityLog {
        id: number;
        log_name: string;
        description: string;
        subject_type: string | null;
        subject_id: string | null;
        causer_type: string | null;
        causer_id: string | null;
        event: string | null;
        attribute_changes: {
            old?: Record<string, unknown>;
            attributes?: Record<string, unknown>;
        } | null;
        properties: Record<string, unknown> | null;
        created_at: string;
        updated_at: string;
        causer?: { id: string; name?: string; email?: string } | null;
        subject?: Record<string, unknown> | null;
    }

    interface Props {
        data: ActivityLog;
    }

    const props = defineProps<Props>();

    function modelShortName(fqcn: string | null): string {
        if (!fqcn) return '—';
        const parts = fqcn.split('\\');
        return parts[parts.length - 1];
    }

    const eventColorMap: Record<string, string> = {
        created: 'text-green-700 bg-green-100 dark:text-green-400 dark:bg-green-900/30',
        updated: 'text-blue-700 bg-blue-100 dark:text-blue-400 dark:bg-blue-900/30',
        deleted: 'text-red-700 bg-red-100 dark:text-red-400 dark:bg-red-900/30',
    };

    const eventClass = computed(
        () =>
            eventColorMap[props.data.event ?? ''] ??
            'text-surface-600 bg-surface-100 dark:text-surface-400 dark:bg-surface-800',
    );

    const changedKeys = computed(() => {
        const attrs = props.data.attribute_changes?.attributes ?? {};
        return Object.keys(attrs);
    });

    // ── Credential masking (defense in depth) ────────────────────────────────
    // The backend stopped recording these attributes (see the deny list in
    // Lvntr\StarterKit\Traits\HasActivityLogging::SENSITIVE_LOG_ATTRIBUTES),
    // but rows written BEFORE that fix — or by a consumer model that rewrote
    // getActivitylogOptions() — can still carry a password hash or a token.
    // This screen therefore never prints such a value, whatever the row holds.
    // Keep both lists in sync with the trait.
    const SENSITIVE_KEYS = new Set(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes']);
    const SENSITIVE_KEY_SUFFIXES = ['_token', '_secret'];
    const MASK = '••••••';

    function isSensitiveKey(key: string): boolean {
        const normalized = key.toLowerCase();
        return SENSITIVE_KEYS.has(normalized) || SENSITIVE_KEY_SUFFIXES.some((suffix) => normalized.endsWith(suffix));
    }

    function formatValue(val: unknown, key?: string): string {
        // Masked unconditionally: reporting "—" for an absent sensitive key
        // would still disclose whether a value was stored.
        if (key !== undefined && isSensitiveKey(key)) return MASK;
        if (val === null || val === undefined) return '—';
        if (typeof val === 'object') return JSON.stringify(redactProperties(val));
        return String(val);
    }

    // Custom properties are rendered as a raw JSON dump, so the mask has to be
    // applied to the structure itself — including nested `attributes` / `old`
    // objects left behind by older activity-log rows.
    function redactProperties(value: unknown): unknown {
        if (Array.isArray(value)) return value.map(redactProperties);

        if (value !== null && typeof value === 'object') {
            return Object.fromEntries(
                Object.entries(value as Record<string, unknown>).map(([key, val]) => [
                    key,
                    isSensitiveKey(key) ? MASK : redactProperties(val),
                ]),
            );
        }

        return value;
    }

    const safeProperties = computed(() => redactProperties(props.data.properties ?? {}));
</script>

<template>
    <div class="space-y-5">
        <!-- Header info -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <span class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500">{{
                    $t('sk-activity-log.event')
                }}</span>
                <span
                    class="mt-1 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold"
                    :class="eventClass"
                >
                    {{ data.event ?? '—' }}
                </span>
            </div>
            <div>
                <span class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500">{{
                    $t('sk-activity-log.log_name')
                }}</span>
                <span class="mt-1 block text-sm text-surface-800 dark:text-surface-200">{{ data.log_name }}</span>
            </div>
            <div>
                <span class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500">{{
                    $t('sk-activity-log.model')
                }}</span>
                <span class="mt-1 block text-sm text-surface-800 dark:text-surface-200">
                    {{ modelShortName(data.subject_type) }}
                    <span v-if="data.subject_id" class="text-surface-400">#{{ data.subject_id }}</span>
                </span>
            </div>
            <div>
                <span class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500">{{
                    $t('sk-activity-log.causer')
                }}</span>
                <span class="mt-1 block text-sm text-surface-800 dark:text-surface-200">
                    <template v-if="data.causer">
                        {{ data.causer.name ?? data.causer.email ?? data.causer_id }}
                    </template>
                    <template v-else>
                        <span class="text-surface-400">System</span>
                    </template>
                </span>
            </div>
            <div>
                <span class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500">{{
                    $t('sk-activity-log.date')
                }}</span>
                <span class="mt-1 block text-sm text-surface-800 dark:text-surface-200">
                    {{
                        formatDateTime(data.created_at, {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit',
                        })
                    }}
                </span>
            </div>
            <div>
                <span class="block text-xs font-medium uppercase text-surface-400 dark:text-surface-500">
                    {{ $t('sk-activity-log.description') }}
                </span>
                <span class="mt-1 block text-sm text-surface-800 dark:text-surface-200">{{ data.description }}</span>
            </div>
        </div>

        <!-- Changes table -->
        <div v-if="changedKeys.length > 0">
            <h3 class="mb-2 text-sm font-semibold text-surface-700 dark:text-surface-300">
                {{ $t('sk-activity-log.changes') }}
            </h3>
            <div class="overflow-hidden rounded-lg border border-surface-200 dark:border-surface-700">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-surface-50 dark:bg-surface-800">
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-surface-500">
                                {{ $t('sk-activity-log.field') }}
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-surface-500">
                                {{ $t('sk-activity-log.old') }}
                            </th>
                            <th class="px-3 py-2 text-left text-xs font-medium uppercase text-surface-500">
                                {{ $t('sk-activity-log.new') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="key in changedKeys"
                            :key="key"
                            class="border-t border-surface-200 dark:border-surface-700"
                        >
                            <td class="px-3 py-2 font-medium text-surface-700 dark:text-surface-300">
                                {{ key }}
                            </td>
                            <td class="px-3 py-2 text-red-600 dark:text-red-400">
                                {{ formatValue(data.attribute_changes?.old?.[key], key) }}
                            </td>
                            <td class="px-3 py-2 text-green-600 dark:text-green-400">
                                {{ formatValue(data.attribute_changes?.attributes?.[key], key) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Custom properties (withProperties user data) -->
        <div v-if="data.properties && Object.keys(data.properties).length > 0">
            <h3 class="mb-2 text-sm font-semibold text-surface-700 dark:text-surface-300">
                {{ $t('sk-activity-log.properties') }}
            </h3>
            <pre
                class="overflow-auto rounded bg-surface-50 p-3 text-xs text-surface-700 dark:bg-surface-800 dark:text-surface-300"
            >{{ JSON.stringify(safeProperties, null, 2) }}</pre>
        </div>
    </div>
</template>
