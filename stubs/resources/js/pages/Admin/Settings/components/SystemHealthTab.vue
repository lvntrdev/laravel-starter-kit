<script setup lang="ts">
    import systemHealth from '@/routes/system-health';
    import { useApi } from '@/composables/useApi';
    import { trans } from 'laravel-vue-i18n';
    import { Button } from 'primevue';
    import SkCard from '@lvntr/components/ui/SkCard.vue';
    import { useToast } from 'primevue/usetoast';
    import { computed, onMounted, ref, watch } from 'vue';

    interface DoctorCheck {
        name: string;
        status: 'ok' | 'warn' | 'fail';
        message: string;
        hint: string;
    }

    interface DoctorSummary {
        ok: number;
        warn: number;
        fail: number;
        total: number;
    }

    interface DoctorReport {
        version: number;
        generated_at: string;
        summary: DoctorSummary;
        checks: DoctorCheck[];
    }

    interface Props {
        report?: DoctorReport | null;
    }

    const props = withDefaults(defineProps<Props>(), {
        report: null,
    });

    const api = useApi({ toast: false });
    const toast = useToast();
    const running = ref(false);
    const localReport = ref<DoctorReport | null>(props.report);

    watch(
        () => props.report,
        (val) => {
            if (val) localReport.value = val;
        },
    );

    function formatGeneratedAt(iso: string): string {
        try {
            return new Date(iso).toLocaleString(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            });
        } catch {
            return iso;
        }
    }

    const statusConfig = {
        ok: {
            badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-500/30',
            icon: 'pi pi-check-circle text-emerald-500',
            row: '',
        },
        warn: {
            badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/30',
            icon: 'pi pi-exclamation-triangle text-amber-500',
            row: 'bg-amber-50/40 dark:bg-amber-500/5',
        },
        fail: {
            badge: 'bg-red-50 text-red-700 ring-1 ring-red-200 dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/30',
            icon: 'pi pi-times-circle text-red-500',
            row: 'bg-red-50/40 dark:bg-red-500/5',
        },
    } as const;

    function badgeClass(status: DoctorCheck['status']): string {
        return statusConfig[status].badge;
    }

    function iconClass(status: DoctorCheck['status']): string {
        return statusConfig[status].icon;
    }

    function rowClass(status: DoctorCheck['status']): string {
        return statusConfig[status].row;
    }

    function statusLabel(status: DoctorCheck['status']): string {
        return trans(`sk-system-health.status_${status}`);
    }

    const summaryCards = computed(() => [
        {
            key: 'ok',
            label: trans('sk-system-health.status_ok'),
            value: localReport.value?.summary.ok ?? 0,
            bg: 'bg-emerald-50 dark:bg-emerald-500/10',
            text: 'text-emerald-700 dark:text-emerald-400',
            icon: 'pi pi-check-circle',
            iconColor: 'text-emerald-500',
        },
        {
            key: 'warn',
            label: trans('sk-system-health.status_warn'),
            value: localReport.value?.summary.warn ?? 0,
            bg: 'bg-amber-50 dark:bg-amber-500/10',
            text: 'text-amber-700 dark:text-amber-400',
            icon: 'pi pi-exclamation-triangle',
            iconColor: 'text-amber-500',
        },
        {
            key: 'fail',
            label: trans('sk-system-health.status_fail'),
            value: localReport.value?.summary.fail ?? 0,
            bg: 'bg-red-50 dark:bg-red-500/10',
            text: 'text-red-700 dark:text-red-400',
            icon: 'pi pi-times-circle',
            iconColor: 'text-red-500',
        },
    ]);

    onMounted(() => {
        if (!localReport.value) {
            runChecks();
        }
    });

    async function runChecks(): Promise<void> {
        if (running.value) return;

        running.value = true;

        try {
            const data = await api.post<{ report: DoctorReport; type: string; message: string }>(
                systemHealth.run.url(),
            );

            localReport.value = data.report;

            toast.add({
                severity: data.type === 'error' ? 'error' : data.type === 'warning' ? 'warn' : 'success',
                summary: data.message,
                group: 'bc',
                life: 5000,
            });
        } catch {
            toast.add({
                severity: 'error',
                summary: trans('sk-system-health.load_error'),
                group: 'bc',
                life: 5000,
            });
        } finally {
            running.value = false;
        }
    }
</script>

<template>
    <SkCard>
        <template #title>{{ $t('sk-system-health.title') }}</template>
        <template #subtitle>
            {{ $t('sk-system-health.subtitle') }}
        </template>
        <template #actions>
            <Button
                :label="running ? $t('sk-system-health.running') : $t('sk-system-health.run_button')"
                icon="pi pi-refresh"
                :loading="running"
                :disabled="running"
                size="small"
                @click="runChecks"
            />
        </template>
        <template #content>
            <div
                v-if="running && !localReport"
                class="flex flex-col items-center gap-3 py-16 text-surface-400 dark:text-surface-500"
            >
                <i class="pi pi-spin pi-spinner text-4xl" />
                <p class="text-base">
                    {{ $t('sk-system-health.running') }}
                </p>
            </div>

            <template v-else>
                <p
                    v-if="localReport"
                    class="mb-4 text-base text-surface-500 dark:text-surface-400"
                >
                    <i class="pi pi-clock mr-1" />
                    {{
                        $t('sk-system-health.generated_at', {
                            time: formatGeneratedAt(localReport.generated_at),
                        })
                    }}
                </p>

                <section class="mb-4 grid grid-cols-3 gap-4">
                    <div
                        v-for="card in summaryCards"
                        :key="card.key"
                        class="flex items-center gap-4 rounded-xl border border-surface-200 p-4 dark:border-surface-700"
                        :class="card.bg"
                    >
                        <div
                            class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-surface-0/60 dark:bg-surface-900/40"
                        >
                            <i :class="[card.icon, card.iconColor]" class="text-xl" />
                        </div>
                        <div>
                            <p class="text-base font-medium" :class="card.text">
                                {{ card.label }}
                            </p>
                            <p class="text-2xl font-bold text-surface-900 dark:text-surface-0">
                                {{ card.value }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900"
                >
                    <div
                        v-if="!localReport?.checks || localReport.checks.length === 0"
                        class="flex flex-col items-center gap-3 py-16 text-surface-400 dark:text-surface-500"
                    >
                        <i class="pi pi-info-circle text-4xl" />
                        <p class="text-base">
                            {{ $t('sk-system-health.no_results') }}
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-800/50">
                                <tr class="text-left text-base font-medium uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                    <th class="w-10 px-4 py-3" />
                                    <th class="px-4 py-3">
                                        {{ $t('sk-system-health.col_check') }}
                                    </th>
                                    <th class="w-28 px-4 py-3">
                                        {{ $t('sk-system-health.col_status') }}
                                    </th>
                                    <th class="px-4 py-3">
                                        {{ $t('sk-system-health.col_message') }}
                                    </th>
                                    <th class="px-4 py-3">
                                        {{ $t('sk-system-health.col_hint') }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-surface-200 dark:divide-surface-700">
                                <tr
                                    v-for="check in localReport.checks"
                                    :key="check.name"
                                    :class="rowClass(check.status)"
                                >
                                    <td class="px-4 py-3">
                                        <i :class="iconClass(check.status)" class="text-base" />
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="text-base font-medium text-surface-900 dark:text-surface-0">
                                            {{ check.name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-base font-medium"
                                            :class="badgeClass(check.status)"
                                        >
                                            {{ statusLabel(check.status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-base text-surface-700 dark:text-surface-300">
                                        {{ check.message }}
                                    </td>
                                    <td class="px-4 py-3 text-base text-surface-500 dark:text-surface-400">
                                        <span v-if="check.hint">{{ check.hint }}</span>
                                        <span v-else class="text-surface-300 dark:text-surface-600">&mdash;</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </template>
    </SkCard>
</template>
