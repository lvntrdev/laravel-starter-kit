<script setup lang="ts">
    import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import { usePage } from '@inertiajs/vue3';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        modelValue: string;
    }

    const props = defineProps<Props>();
    const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

    const page = usePage<{ turnstile?: { enabled: boolean; site_key: string | null } }>();
    const container = ref<HTMLDivElement | null>(null);
    const widgetId = ref<string | null>(null);
    const loadFailed = ref(false);

    const SCRIPT_ID = 'cf-turnstile-script';
    const SCRIPT_SRC = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

    function loadScript(): Promise<void> {
        return new Promise((resolve, reject) => {
            if (window.turnstile) {
                resolve();
                return;
            }
            const existing = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;
            if (existing) {
                existing.addEventListener('load', () => resolve(), { once: true });
                existing.addEventListener('error', () => reject(new Error('script-load-failed')), { once: true });
                return;
            }
            const s = document.createElement('script');
            s.id = SCRIPT_ID;
            s.src = SCRIPT_SRC;
            s.async = true;
            s.defer = true;
            s.onload = () => resolve();
            s.onerror = () => reject(new Error('script-load-failed'));
            document.head.appendChild(s);
        });
    }

    async function render() {
        const ts = page.props.turnstile;
        if (!container.value || !ts?.enabled || !ts.site_key) {
            return;
        }

        try {
            await loadScript();
        } catch {
            loadFailed.value = true;
            emit('update:modelValue', '');
            return;
        }

        if (!window.turnstile) {
            loadFailed.value = true;
            return;
        }

        try {
            widgetId.value = window.turnstile.render(container.value, {
                sitekey: ts.site_key,
                callback: (token: string) => {
                    loadFailed.value = false;
                    emit('update:modelValue', token);
                },
                'error-callback': () => {
                    loadFailed.value = true;
                    emit('update:modelValue', '');
                },
                'expired-callback': () => emit('update:modelValue', ''),
            });
        } catch {
            loadFailed.value = true;
        }
    }

    function reset() {
        if (widgetId.value && window.turnstile) {
            window.turnstile.reset(widgetId.value);
            emit('update:modelValue', '');
        }
    }

    defineExpose({ reset, loadFailed });

    onMounted(render);
    onBeforeUnmount(() => {
        if (widgetId.value && window.turnstile) {
            window.turnstile.remove(widgetId.value);
        }
    });

    watch(
        () => props.modelValue,
        (val) => {
            if (!val && widgetId.value && window.turnstile) {
                window.turnstile.reset(widgetId.value);
            }
        },
    );
</script>

<template>
    <div v-if="page.props.turnstile?.enabled" class="cf-turnstile-wrapper mt-2">
        <div ref="container" />
        <small v-if="loadFailed" class="auth-form__error" role="alert">
            {{ trans('sk-auth.turnstile.load_failed') }}
        </small>
    </div>
</template>
