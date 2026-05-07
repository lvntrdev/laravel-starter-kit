<script setup lang="ts">
    /**
     * Personal Access Token oluşturma formu.
     *
     * Backend ApiResponse (JSON) döndürür — Inertia değil.
     * SkForm external (v-model) modunda kullanılır; submit useApi ile yapılır.
     */
    import { useApi } from '@/composables/useApi';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import apiTokens from '@/routes/api-tokens';
    import { trans } from 'laravel-vue-i18n';
    import { Button } from 'primevue';

    interface ScopeOption {
        id: string;
        description: string;
    }

    interface Props {
        availableScopes?: ScopeOption[];
        onCreated?: (accessToken: string) => void;
        onCancel?: () => void;
    }

    const props = withDefaults(defineProps<Props>(), {
        availableScopes: () => [],
    });

    const emit = defineEmits<{
        created: [accessToken: string];
        cancel: [];
    }>();

    const api = useApi();
    const loading = ref(false);
    const errors = ref<Record<string, string[]>>({});

    const form = ref<Record<string, unknown>>({
        name: '',
        scopes: [],
    });

    const flatErrors = computed(() =>
        Object.fromEntries(
            Object.entries(errors.value).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)]),
        ),
    );

    const scopeOptions = computed(() =>
        props.availableScopes.map((s) => ({
            label: s.description ? `${s.id} — ${s.description}` : s.id,
            value: s.id,
        })),
    );

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(1)
            .isCard(false)
            .addFields(
                FB.inputText().key('name'),
                FB.multiselect()
                    .key('scopes')
                    .options(scopeOptions.value)
                    .filter(true)
                    .optional()
                    .visible(() => scopeOptions.value.length > 0),
            )
            .build(),
    );

    async function handleSubmit() {
        errors.value = {};
        loading.value = true;
        try {
            const result = await api.post<{ access_token: string | null }>(apiTokens.store.url(), {
                name: form.value.name,
                scopes: form.value.scopes,
            });
            emit('created', result.access_token ?? '');
            props.onCreated?.(result.access_token ?? '');
        } catch (err: unknown) {
            if (err && typeof err === 'object' && 'body' in err) {
                const apiErr = err as { body: { errors?: Record<string, string[]> } };
                if (apiErr.body?.errors) errors.value = apiErr.body.errors;
            }
        } finally {
            loading.value = false;
        }
    }

    function handleCancel() {
        emit('cancel');
        props.onCancel?.();
    }
</script>

<template>
    <SkForm v-model="form" :config="formConfig" :errors="flatErrors">
        <template #actions>
            <Button
                type="button"
                :label="$t('sk-button.cancel')"
                severity="secondary"
                outlined
                :disabled="loading"
                @click="handleCancel"
            />
            <Button
                :label="$t('sk-api-tokens.create')"
                icon="pi pi-key"
                :loading="loading"
                @click="handleSubmit"
            />
        </template>
    </SkForm>
</template>
