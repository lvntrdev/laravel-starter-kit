<script setup lang="ts">
    import { computed } from 'vue';

    const props = defineProps<{
        usage: { used_bytes: number; quota_bytes: number };
    }>();

    function formatBytes(bytes: number): string {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
        if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        return `${(bytes / (1024 * 1024 * 1024)).toFixed(1)} GB`;
    }

    const isUnlimited = computed(() => !props.usage.quota_bytes || props.usage.quota_bytes === 0);

    const percent = computed(() => {
        if (isUnlimited.value) return 0;
        return Math.min(100, Math.round((props.usage.used_bytes / props.usage.quota_bytes) * 100));
    });

    const radius = 38;
    const circumference = 2 * Math.PI * radius;
    const strokeDashoffset = computed(() => circumference * (1 - percent.value / 100));

    const strokeColor = computed(() => {
        if (percent.value >= 90) return '#ef4444';
        if (percent.value >= 70) return '#f59e0b';
        return '#94a3b8';
    });
</script>

<template>
    <div class="mt-3 rounded-2xl bg-slate-100 p-5 text-center dark:bg-surface-800">
        <!-- Ring -->
        <div class="relative mx-auto mb-3 h-24 w-24">
            <svg width="96" height="96" viewBox="0 0 96 96" class="-rotate-90">
                <!-- Track -->
                <circle cx="48" cy="48" :r="radius" fill="none" stroke="#e2e8f0" stroke-width="7" />
                <!-- Progress -->
                <circle
                    v-if="!isUnlimited && percent > 0"
                    cx="48"
                    cy="48"
                    :r="radius"
                    fill="none"
                    stroke-width="7"
                    stroke-linecap="round"
                    :stroke="strokeColor"
                    :stroke-dasharray="circumference"
                    :stroke-dashoffset="strokeDashoffset"
                    class="transition-all duration-500"
                />
            </svg>
            <!-- Center % -->
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-sm font-semibold text-slate-500 dark:text-surface-300">
                    {{ isUnlimited ? '∞' : `${percent}%` }}
                </span>
            </div>
        </div>

        <!-- Title -->
        <p class="text-sm font-bold text-slate-700 dark:text-surface-100">
            {{ $t('sk-setting.storage.usage_card_title') }}
        </p>

        <!-- Subtitle -->
        <p class="mt-0.5 text-xs text-slate-400 dark:text-surface-400">
            <template v-if="!isUnlimited">
                {{ formatBytes(usage.used_bytes) }} / {{ formatBytes(usage.quota_bytes) }}
            </template>
            <template v-else>{{ $t('sk-setting.storage.unlimited') }}</template>
        </p>
    </div>
</template>
