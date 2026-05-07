<script setup lang="ts">
    /**
     * Personal Access Token oluşturma formu.
     *
     * Backend ApiResponse (JSON) döndürür — Inertia değil.
     * useApi ile POST yapılır; access_token tek seferlik döner.
     */
    import { useApi } from '@/composables/useApi';
    import apiTokens from '@/routes/api-tokens';
    import { trans } from 'laravel-vue-i18n';
    import { Button, Dialog, InputText, MultiSelect } from 'primevue';

    interface ScopeOption {
        id: string;
        description: string;
    }

    interface Props {
        visible: boolean;
        availableScopes?: ScopeOption[];
    }

    const props = withDefaults(defineProps<Props>(), {
        availableScopes: () => [],
    });

    const emit = defineEmits<{
        close: [];
        /** Token oluşturuldu — access_token ile */
        created: [accessToken: string];
    }>();

    const api = useApi();
    const loading = ref(false);
    const errors = ref<Record<string, string[]>>({});

    const form = reactive({
        name: '',
        scopes: [] as string[],
    });

    const scopeOptions = computed(() =>
        props.availableScopes.map((s) => ({
            label: s.description ? `${s.id} — ${s.description}` : s.id,
            value: s.id,
        })),
    );

    function reset() {
        form.name = '';
        form.scopes = [];
        errors.value = {};
    }

    /** Dialog açıldığında formu sıfırla */
    watch(
        () => props.visible,
        (val) => {
            if (val) reset();
        },
    );

    async function handleSubmit() {
        errors.value = {};
        loading.value = true;

        try {
            const result = await api.post<{ id: string; access_token: string | null }>(apiTokens.store.url(), {
                name: form.name,
                scopes: form.scopes,
            });

            emit('created', result.access_token ?? '');
        } catch (err: unknown) {
            if (err && typeof err === 'object' && 'body' in err) {
                const apiErr = err as { body: { errors?: Record<string, string[]> } };
                if (apiErr.body?.errors) {
                    errors.value = apiErr.body.errors;
                }
            }
        } finally {
            loading.value = false;
        }
    }

    function handleClose() {
        emit('close');
    }

    function firstError(key: string): string | undefined {
        return errors.value[key]?.[0];
    }
</script>

<template>
    <Dialog
        :visible="visible"
        :header="$t('sk-api-tokens.create')"
        :closable="true"
        :dismissable-mask="false"
        :modal="true"
        style="width: 520px"
        @update:visible="handleClose"
    >
        <form class="space-y-5" @submit.prevent="handleSubmit">
            <!-- Name -->
            <div class="flex flex-col gap-1.5">
                <label for="at_name" class="text-base font-medium">
                    {{ $t('sk-api-tokens.fields.name') }}
                    <span class="text-red-500">*</span>
                </label>
                <InputText
                    id="at_name"
                    v-model="form.name"
                    class="w-full"
                    :invalid="!!firstError('name')"
                    :disabled="loading"
                    :auto-focus="true"
                />
                <small v-if="firstError('name')" class="text-base text-red-500">{{ firstError('name') }}</small>
            </div>

            <!-- Scopes -->
            <div v-if="scopeOptions.length > 0" class="flex flex-col gap-1.5">
                <label for="at_scopes" class="text-base font-medium">{{ $t('sk-api-tokens.fields.scopes') }}</label>
                <MultiSelect
                    id="at_scopes"
                    v-model="form.scopes"
                    :options="scopeOptions"
                    option-label="label"
                    option-value="value"
                    :filter="true"
                    class="w-full"
                    :disabled="loading"
                    display="chip"
                />
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    type="button"
                    :label="$t('sk-button.cancel')"
                    severity="secondary"
                    outlined
                    :disabled="loading"
                    @click="handleClose"
                />
                <Button
                    type="button"
                    :label="$t('sk-api-tokens.create')"
                    icon="pi pi-key"
                    :loading="loading"
                    @click="handleSubmit"
                />
            </div>
        </template>
    </Dialog>
</template>
