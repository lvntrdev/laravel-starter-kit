<script setup lang="ts">
    /**
     * ApiClient oluşturma / düzenleme formu.
     *
     * Backend endpoint'leri ApiResponse (JSON) döndürür — Inertia değil.
     * Bu nedenle SkForm external (v-model) modunda kullanılır; submit useApi ile yapılır.
     */
    import { useApi } from '@/composables/useApi';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import apiClients from '@/routes/api-clients';
    import { trans } from 'laravel-vue-i18n';
    import { Button, InputChips } from 'primevue';
    import { useToast } from 'primevue/usetoast';

    interface Props {
        clientId?: string | null;
        inDialog?: boolean;
        onSuccess?: () => void;
        onCancel?: () => void;
        onCreated?: (plainSecret: string) => void;
    }

    const props = withDefaults(defineProps<Props>(), {
        clientId: null,
        inDialog: false,
    });

    const emit = defineEmits<{
        created: [plainSecret: string];
        success: [];
        cancel: [];
    }>();

    const api = useApi();
    const toast = useToast();
    const isEdit = computed(() => !!props.clientId);
    const loading = ref(false);
    const dataLoading = ref(false);
    const errors = ref<Record<string, string[]>>({});

    const form = ref<Record<string, unknown>>({
        name: '',
        grant_type: 'client_credentials',
        redirect_uris: [],
    });

    const flatErrors = computed(() =>
        Object.fromEntries(
            Object.entries(errors.value).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)]),
        ),
    );

    onMounted(async () => {
        if (!isEdit.value || !props.clientId) return;

        dataLoading.value = true;
        try {
            const client = await api.get<{
                name: string;
                grant_types: string[];
                redirect_uris: string[];
            }>(apiClients.data.url(props.clientId));

            form.value = {
                name: client.name ?? '',
                grant_type: client.grant_types?.[0] ?? 'client_credentials',
                redirect_uris: client.redirect_uris ?? [],
            };
        } catch {
            toast.add({
                severity: 'error',
                summary: trans('sk-api-clients.errors.fetch_failed_summary'),
                detail: trans('sk-api-clients.errors.fetch_failed'),
                group: 'bc',
                life: 5000,
            });
            emit('cancel');
            props.onCancel?.();
        } finally {
            dataLoading.value = false;
        }
    });

    const formConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .inDialog(props.inDialog)
            .isCard(false)
            .addFields(
                FB.inputText().key('name').class('col-span-full'),
                FB.select()
                    .key('grant_type')
                    .options([
                        { label: trans('sk-api-clients.grant_types.authorization_code'), value: 'authorization_code' },
                        { label: trans('sk-api-clients.grant_types.client_credentials'), value: 'client_credentials' },
                    ])
                    .visible(() => !isEdit.value),
                FB.slot()
                    .key('redirect_uris')
                    .visible((values) => values.grant_type === 'authorization_code')
                    .class('col-span-full'),
            )
            .build(),
    );

    async function handleSubmit() {
        errors.value = {};
        loading.value = true;
        try {
            if (isEdit.value) {
                await api.put(apiClients.update.url(props.clientId!), {
                    name: form.value.name,
                    redirect_uris: form.value.redirect_uris,
                });
                emit('success');
                props.onSuccess?.();
            } else {
                const result = await api.post<{ plain_secret: string | null }>(
                    apiClients.store.url(),
                    form.value,
                );
                emit('created', result.plain_secret ?? '');
                props.onCreated?.(result.plain_secret ?? '');
            }
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
    <div v-if="dataLoading" class="flex items-center justify-center p-10">
        <i class="pi pi-spin pi-spinner text-2xl text-surface-400" />
    </div>
    <SkForm v-else v-model="form" :config="formConfig" :errors="flatErrors">
        <template #redirect_uris>
            <div class="flex flex-col gap-1.5">
                <label class="text-base font-medium">
                    {{ $t('sk-api-clients.fields.redirect_uris') }}
                </label>
                <InputChips
                    v-model="(form as any).redirect_uris"
                    :add-on-blur="true"
                    separator=","
                    class="w-full"
                    :invalid="!!flatErrors['redirect_uris']"
                />
                <small class="text-base text-surface-400">
                    {{ $t('sk-api-clients.fields.redirect_uris_hint') }}
                </small>
                <small v-if="flatErrors['redirect_uris']" class="text-base text-red-500">
                    {{ flatErrors['redirect_uris'] }}
                </small>
            </div>
        </template>
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
                :label="isEdit ? $t('sk-button.update') : $t('sk-button.create')"
                :icon="isEdit ? 'pi pi-check' : 'pi pi-plus'"
                :loading="loading"
                @click="handleSubmit"
            />
        </template>
    </SkForm>
</template>
