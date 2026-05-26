<script setup lang="ts">
    import { computed, useAttrs, useSlots } from 'vue';

    defineOptions({ name: 'SkCard', inheritAttrs: false });

    interface Props {
        /** Shorthand for the title slot — rendered as plain text. Pass a translated string. */
        title?: string;
        /** Shorthand for the subtitle slot — rendered as plain text. Pass a translated string. */
        subtitle?: string;
        /**
         * Render the card without background, border, shadow or padding —
         * useful as an invisible wrapper inside dialogs or other surfaces.
         */
        transparent?: boolean;
        /**
         * Show a bottom divider line below the caption block (title + subtitle)
         * so it reads as a distinct header above the content. Defaults to `true`.
         */
        divider?: boolean;
        /** Extra PrimeVue Card passthrough. Merged with internal pt; consumer wins on conflicts. */
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        pt?: Record<string, any>;
    }

    const props = withDefaults(defineProps<Props>(), {
        title: undefined,
        subtitle: undefined,
        transparent: false,
        divider: true,
        pt: undefined,
    });

    const slots = useSlots();
    const attrs = useAttrs();

    const hasTitle = computed(() => !!props.title || !!slots.title || !!slots['title-end']);
    const hasSubtitle = computed(() => !!props.subtitle || !!slots.subtitle);

    const rootClass = computed(() => {
        const classes: unknown[] = ['sk-card'];
        if (props.transparent) classes.push('sk-card--transparent');
        if (props.divider && (hasTitle.value || hasSubtitle.value)) {
            classes.push('sk-card--divider');
        }
        if (attrs.class) classes.push(attrs.class);
        return classes;
    });

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const mergedPt = computed<Record<string, any>>(() => {
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const base: Record<string, any> = {
            root: { class: rootClass.value },
        };
        if (props.transparent) {
            base.root = {
                ...base.root,
                style: 'background: transparent; box-shadow: none; border: 0; padding: 0',
            };
            base.body = { style: 'padding: 0' };
            base.content = { style: 'padding: 0' };
        }
        if (!props.pt) return base;
        // Shallow merge — consumer overrides win.
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const out: Record<string, any> = { ...base };
        for (const key of Object.keys(props.pt)) {
            out[key] = { ...(base[key] ?? {}), ...props.pt[key] };
        }
        return out;
    });
</script>

<template>
    <Card :pt="mergedPt">
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>

        <template v-if="hasTitle" #title>
            <div class="sk-card__title-row">
                <span class="sk-card__title-text">
                    <slot name="title">{{ title }}</slot>
                </span>
                <span v-if="$slots['title-end']" class="sk-card__title-end">
                    <slot name="title-end" />
                </span>
            </div>
        </template>

        <template v-if="hasSubtitle" #subtitle>
            <slot name="subtitle">{{ subtitle }}</slot>
        </template>

        <template #content>
            <slot name="content">
                <slot />
            </slot>
        </template>

        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </Card>
</template>
