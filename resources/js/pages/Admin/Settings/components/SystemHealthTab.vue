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

    const statusConfig = {
        ok: {
            badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',
            icon: 'pi pi-check-circle text-emerald-500',
            row: '',
            banner: 'bg-red-600',
        },
        warn: {
            badge: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',
            icon: 'pi pi-exclamation-triangle text-amber-500',
            row: 'bg-amber-50/50 dark:bg-amber-500/5',
            banner: 'bg-amber-500',
        },
        fail: {
            badge: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
            icon: 'pi pi-times-circle text-red-500',
            row: 'bg-red-50/50 dark:bg-red-500/5',
            banner: 'bg-red-600',
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

    const problemChecks = computed<DoctorCheck[]>(() =>
        (localReport.value?.checks ?? []).filter((c) => c.status !== 'ok'),
    );

    function bannerClass(status: DoctorCheck['status']): string {
        return statusConfig[status].banner;
    }

    function bannerIcon(status: DoctorCheck['status']): string {
        return status === 'warn' ? 'pi pi-exclamation-triangle' : 'pi pi-times-circle';
    }

    function bannerTitle(check: DoctorCheck): string {
        return trans(`sk-system-health.banner_${check.status === 'warn' ? 'warn' : 'fail'}_title`, {
            name: check.name,
        });
    }

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
    <SkCard flush>
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
                outlined
                @click="runChecks"
            />
        </template>
        <template #content>
            <div
                v-if="running && !localReport"
                class="flex flex-col items-center gap-3 px-6 py-16 text-surface-400 dark:text-surface-500"
            >
                <i class="pi pi-spin pi-spinner text-4xl" />
                <p class="text-base">
                    {{ $t('sk-system-health.running') }}
                </p>
            </div>

            <div v-else class="flex flex-col">
                <section v-if="problemChecks.length" class="flex flex-col gap-2.5 px-6 pt-5 pb-4">
                    <div
                        v-for="check in problemChecks"
                        :key="'banner-' + check.name"
                        class="flex items-center gap-3 rounded-md px-4 py-3 text-white"
                        :class="bannerClass(check.status)"
                    >
                        <span class="grid h-9 w-9 flex-none place-items-center rounded-full bg-white/20 text-[15px]">
                            <i :class="bannerIcon(check.status)" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-base font-semibold leading-tight">
                                {{ bannerTitle(check) }}
                            </p>
                            <p class="mt-0.5 text-[13px] leading-relaxed text-white/90">
                                {{ check.message }}
                                <template v-if="check.hint">
                                    &nbsp;{{ $t('sk-system-health.banner_hint_prefix') }}:
                                    <code class="rounded bg-white/20 px-1.5 py-px font-mono text-xs">{{ check.hint }}</code>
                                </template>
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    :class="problemChecks.length ? 'border-t border-surface-200 dark:border-surface-700' : ''"
                >
                    <div
                        v-if="!localReport?.checks || localReport.checks.length === 0"
                        class="flex flex-col items-center gap-3 px-6 py-16 text-surface-400 dark:text-surface-500"
                    >
                        <i class="pi pi-info-circle text-4xl" />
                        <p class="text-base">
                            {{ $t('sk-system-health.no_results') }}
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="border-b border-surface-200 bg-surface-50 dark:border-surface-700 dark:bg-surface-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                                    <th class="px-6 py-3">
                                        {{ $t('sk-system-health.col_check') }}
                                    </th>
                                    <th class="w-32 px-6 py-3">
                                        {{ $t('sk-system-health.col_status') }}
                                    </th>
                                    <th class="px-6 py-3">
                                        {{ $t('sk-system-health.col_message') }}
                                    </th>
                                    <th class="px-6 py-3">
                                        {{ $t('sk-system-health.col_hint') }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-surface-200 dark:divide-surface-700">
                                <tr
                                    v-for="check in localReport.checks"
                                    :key="check.name"
                                    class="transition-colors hover:bg-surface-50 dark:hover:bg-surface-800/50"
                                    :class="rowClass(check.status)"
                                >
                                    <td class="px-6 py-3.5 whitespace-nowrap">
                                        <div class="flex items-center gap-2.5">
                                            <i :class="iconClass(check.status)" class="text-[15px]" />
                                            <span class="text-base font-semibold text-surface-900 dark:text-surface-0">
                                                {{ check.name }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3.5">
                                        <span
                                            class="inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-xs font-semibold"
                                            :class="badgeClass(check.status)"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-current" />
                                            {{ statusLabel(check.status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-[13px] leading-relaxed text-surface-600 dark:text-surface-300">
                                        {{ check.message }}
                                    </td>
                                    <td class="px-6 py-3.5 text-[13px] text-surface-500 dark:text-surface-400">
                                        <code v-if="check.hint" class="font-mono text-xs">{{ check.hint }}</code>
                                        <span v-else class="text-surface-300 dark:text-surface-600">&mdash;</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </template>
    </SkCard>
</template>
