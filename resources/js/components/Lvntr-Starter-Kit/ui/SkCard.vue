<script setup lang="ts">
    import { computed, useAttrs, useSlots } from 'vue';

    defineOptions({ name: 'SkCard', inheritAttrs: false });

    interface Props {
        /** Shorthand for the title slot — rendered as plain text. Pass a translated string. */
        title?: string;
        /** Shorthand for the subtitle slot — rendered as plain text. Pass a translated string. */
        subtitle?: string;
        /**
         * Render the card without surface — bg, border, radius and padding are
         * all dropped. Useful as an invisible wrapper inside dialogs or other
         * surfaces that already provide their own chrome.
         */
        transparent?: boolean;
        /**
         * Show a bottom divider line below the head (title + subtitle / actions)
         * so it reads as a distinct header above the body. Defaults to `true`.
         */
        divider?: boolean;
        /**
         * Remove the body padding so content sits flush against the card edges.
         * Useful for tables, toolbars or vertical-tab navigation that manage
         * their own internal spacing.
         */
        flush?: boolean;
    }

    const props = withDefaults(defineProps<Props>(), {
        title: undefined,
        subtitle: undefined,
        transparent: false,
        divider: true,
        flush: false,
    });

    const slots = useSlots();
    const attrs = useAttrs();

    // ── Head visibility ─────────────────────────────────────────────────
    const hasTitle = computed(() => !!props.title || !!slots.title);
    const hasSubtitle = computed(() => !!props.subtitle || !!slots.subtitle);
    // `title-end` is a back-compat alias for `actions` — both render to the
    // right-hand region of the head.
    const hasActions = computed(() => !!slots.actions || !!slots['title-end']);
    const hasHeader = computed(() => !!slots.header);
    const hasHead = computed(
        () => hasHeader.value || hasTitle.value || hasSubtitle.value || hasActions.value,
    );

    // Head becomes a flex row only when there is a right-hand region to lay out.
    const isRowHead = computed(() => hasActions.value);

    // ── Foot visibility ─────────────────────────────────────────────────
    const hasFoot = computed(() => !!slots.footer);

    // ── Root class ──────────────────────────────────────────────────────
    const rootClass = computed(() => {
        const classes: unknown[] = ['sk-card'];
        if (props.transparent) classes.push('sk-card--transparent');
        if (props.flush) classes.push('sk-card--flush');
        if (attrs.class) classes.push(attrs.class);
        return classes;
    });

    const headClass = computed(() => {
        const classes: unknown[] = ['sk-card__head'];
        if (isRowHead.value) classes.push('sk-card__head--row');
        // The divider only makes sense when there is a head to separate from the body.
        if (props.divider) classes.push('sk-card__head--divider');
        return classes;
    });
</script>

<template>
    <div :class="rootClass">
        <!-- ── Head ──────────────────────────────────────────────────── -->
        <div v-if="hasHead" :class="headClass">
            <!-- Full custom head override -->
            <slot v-if="hasHeader" name="header" />

            <!-- Structured head: title / subtitle group (+ optional actions) -->
            <template v-else>
                <div class="sk-card__head-main">
                    <div v-if="hasTitle" class="sk-card__title">
                        <slot name="title">{{ title }}</slot>
                    </div>
                    <div v-if="hasSubtitle" class="sk-card__subtitle">
                        <slot name="subtitle">{{ subtitle }}</slot>
                    </div>
                </div>
                <div v-if="hasActions" class="sk-card__actions">
                    <slot name="actions">
                        <slot name="title-end" />
                    </slot>
                </div>
            </template>
        </div>

        <!-- ── Body ──────────────────────────────────────────────────── -->
        <div class="sk-card__body">
            <slot name="content">
                <slot />
            </slot>
        </div>

        <!-- ── Foot ──────────────────────────────────────────────────── -->
        <div v-if="hasFoot" class="sk-card__foot">
            <slot name="footer" />
        </div>
    </div>
</template>
