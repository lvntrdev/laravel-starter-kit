<!-- resources/js/components/SkTag.vue -->
<!-- Tailwind-powered tag component with PrimeVue-compatible API. -->
<script setup lang="ts">
    import { computed } from 'vue';

    export type SkTagColor =
        | 'red'
        | 'orange'
        | 'amber'
        | 'yellow'
        | 'lime'
        | 'green'
        | 'emerald'
        | 'teal'
        | 'cyan'
        | 'sky'
        | 'blue'
        | 'indigo'
        | 'violet'
        | 'purple'
        | 'fuchsia'
        | 'pink'
        | 'rose'
        | 'slate'
        | 'gray'
        | 'zinc'
        | 'neutral'
        | 'stone';

    export type SkTagSeverity = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast';

    const tailwindColors: Set<string> = new Set<string>([
        'red',
        'orange',
        'amber',
        'yellow',
        'lime',
        'green',
        'emerald',
        'teal',
        'cyan',
        'sky',
        'blue',
        'indigo',
        'violet',
        'purple',
        'fuchsia',
        'pink',
        'rose',
        'slate',
        'gray',
        'zinc',
        'neutral',
        'stone',
    ]);

    export interface SkTagProps {
        value?: string;
        icon?: string;
        /** Icon position: 'left' (default) or 'right'. */
        iconPos?: 'left' | 'right';
        color?: SkTagColor;
        /** PrimeVue severity veya doğrudan Tailwind renk adı (ör. 'emerald', 'purple'). */
        severity?: SkTagSeverity | SkTagColor | string;
        soft?: boolean;
        rounded?: boolean;
        outlined?: boolean;
    }

    const props = withDefaults(defineProps<SkTagProps>(), {
        iconPos: 'left',
        soft: false,
        rounded: false,
        outlined: false,
    });

    /** Map PrimeVue severities to Tailwind color keys. */
    const severityColorMap: Record<SkTagSeverity, SkTagColor> = {
        success: 'green',
        info: 'blue',
        warn: 'amber',
        danger: 'red',
        secondary: 'slate',
        contrast: 'zinc',
    };

    const resolvedColor = computed<SkTagColor>(() => {
        if (props.color) return props.color;
        if (props.severity) {
            if (tailwindColors.has(props.severity)) return props.severity as SkTagColor;
            if (props.severity in severityColorMap) return severityColorMap[props.severity as SkTagSeverity];
        }
        return 'slate';
    });

    const cssClass = computed(() => [
        'sk-tag',
        `sk-tag--${resolvedColor.value}`,
        {
            'sk-tag--soft': props.soft,
            'sk-tag--rounded': props.rounded,
            'sk-tag--outlined': props.outlined,
        },
    ]);
</script>

<template>
    <span :class="cssClass">
        <i v-if="icon && iconPos === 'left'" :class="icon" class="sk-tag__icon" />
        <span v-if="value" class="sk-tag__value">{{ value }}</span>
        <slot />
        <i v-if="icon && iconPos === 'right'" :class="icon" class="sk-tag__icon" />
    </span>
</template>
