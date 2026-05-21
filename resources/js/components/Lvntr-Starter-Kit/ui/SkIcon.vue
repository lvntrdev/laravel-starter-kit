<script setup lang="ts">
    /**
     * SkIcon — paket-bağımsız icon renderer (PrimeIcons, FontAwesome, MDI, Lucide,
     * Iconify, ham SVG veya img URL/data URI). Tek string + auto-detect.
     *
     * GÜVENLİK: `icon` propu yalnızca BUILDER CONFIG'ten (developer-controlled)
     * geçirilmek üzere tasarlandı. Kullanıcı kaynaklı string ASLA geçirilmemeli
     * (XSS riski — svg path'i v-html ile render eder).
     */

    import { computed, useAttrs } from 'vue';

    defineOptions({ inheritAttrs: false });

    interface Props {
        /** Icon descriptor: CSS class string, SVG markup veya URL/data URI. */
        icon: string;
        /** Erişilebilirlik etiketi. Belirtilmezse dekoratif kabul edilir. */
        ariaLabel?: string;
    }

    const props = withDefaults(defineProps<Props>(), {
        ariaLabel: undefined,
    });

    const attrs = useAttrs();

    /** Icon tipi: 'svg' | 'img' | 'class' */
    const iconType = computed<'svg' | 'img' | 'class'>(() => {
        if (props.icon.startsWith('<svg')) return 'svg';
        if (/^(https?:|data:)/i.test(props.icon)) return 'img';
        return 'class';
    });
</script>

<template>
    <!-- SVG markup: v-html ile render, span içine sarılır -->
    <span
        v-if="iconType === 'svg'"
        class="sk-icon sk-icon--svg"
        :class="attrs.class"
        :aria-label="ariaLabel"
        role="img"
        v-html="icon"
    />

    <!-- URL veya data URI: img olarak render -->
    <img
        v-else-if="iconType === 'img'"
        :src="icon"
        :class="['sk-icon sk-icon--img', attrs.class]"
        :alt="ariaLabel ?? ''"
    />

    <!-- CSS class: i olarak render (PrimeIcons, FA, MDI, Lucide vb.) -->
    <i
        v-else
        :class="['sk-icon', icon, attrs.class]"
        :aria-label="ariaLabel"
        :aria-hidden="!ariaLabel ? true : undefined"
    />
</template>
