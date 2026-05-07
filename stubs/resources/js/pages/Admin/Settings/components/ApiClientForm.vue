<script setup lang="ts">
    /**
     * ApiClient oluşturma / düzenleme formu.
     *
     * Backend endpoint'leri ApiResponse (JSON) döndürür — Inertia router değil.
     * Bu nedenle useApi ile manuel form submit yapılır.
     */
    import { useApi } from '@/composables/useApi';
    import apiClients from '@/routes/api-clients';
    import { trans } from 'laravel-vue-i18n';
    import { Button, InputText, Select, MultiSelect, InputChips, Message } from 'primevue';
    import { useToast } from 'primevue/usetoast';

    interface ScopeOption {
        id: string;
        description: string;
    }

    interface Props {
        clientId?: string | null;
        inDialog?: boolean;
        availableScopes?: ScopeOption[];
        /** Dialog callback — dialog.open ile inject edilir */
        onSuccess?: () => void;
        onCancel?: () => void;
        /**
         * Create başarılı olduğunda plain_secret ile çağrılır.
         * Index.vue bu callback üzerinden secret modal'ı açar.
         */
        onCreated?: (plainSecret: string) => void;
    }

    const props = withDefaults(defineProps<Props>(), {
        clientId: null,
        inDialog: false,
        availableScopes: () => [],
    });

    const emit = defineEmits<{
        /** store başarılı — plain_secret ile birlikte */
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

    // PAT (Personal Access Token) grant type bu UI'dan kaldırıldı.
    // PAT'lar için ayrı ApiTokens yönetimi kullanılır.
    // Passport personal access client kurulumu: php artisan passport:client --personal
    const grantTypeOptions = [
        { label: trans('sk-api-clients.grant_types.authorization_code'), value: 'authorization_code' },
        { label: trans('sk-api-clients.grant_types.client_credentials'), value: 'client_credentials' },
    ];

    const scopeOptions = computed(() =>
        props.availableScopes.map((s) => ({
            label: s.description ? `${s.id} — ${s.description}` : s.id,
            value: s.id,
        })),
    );

    // ── Form state ────────────────────────────────────────────────────────────

    const form = reactive({
        name: '',
        grant_type: 'client_credentials',
        redirect_uris: [] as string[],
        scopes: [] as string[],
        // confidential alanı kaldırıldı: backend her zaman confidential=true zorlar
    });

    // ── Load existing client data (edit mode) ─────────────────────────────────

    onMounted(async () => {
        if (!isEdit.value || !props.clientId) return;

        dataLoading.value = true;
        try {
            // useApi.get ApiEnvelope'u unwrap eder → data doğrudan client objesidir
            const client = await api.get<{
                id: string;
                name: string;
                grant_types: string[];
                redirect_uris: string[];
                scopes: string[];
            }>(apiClients.data.url(props.clientId));

            form.name = client.name ?? '';
            form.grant_type = client.grant_types?.[0] ?? 'client_credentials';
            form.redirect_uris = client.redirect_uris ?? [];
            form.scopes = client.scopes ?? [];
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

    // ── Submit ────────────────────────────────────────────────────────────────

    async function handleSubmit() {
        errors.value = {};
        loading.value = true;

        const payload = {
            name: form.name,
            grant_type: form.grant_type,
            redirect_uris: form.redirect_uris,
            scopes: form.scopes,
        };

        try {
            if (isEdit.value) {
                await api.put(apiClients.update.url(props.clientId!), {
                    name: form.name,
                    redirect_uris: form.redirect_uris,
                    scopes: form.scopes,
                });
                emit('success');
                props.onSuccess?.();
            } else {
                // useApi.post ApiEnvelope'u unwrap eder → result direkt client objesidir
                const result = await api.post<{ id: string; plain_secret: string | null }>(
                    apiClients.store.url(),
                    payload,
                );
                const plainSecret = result.plain_secret ?? '';
                emit('created', plainSecret);
                // onCreated üzerinden Index.vue'ya secret geçilir
                // onSuccess çağrısı plain_secret modal kapatıldıktan sonra yapılmalı
                props.onCreated?.(plainSecret);
            }
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

    function handleCancel() {
        emit('cancel');
        props.onCancel?.();
    }

    function firstError(key: string): string | undefined {
        return errors.value[key]?.[0];
    }
</script>

<template>
    <div v-if="dataLoading" class="flex items-center justify-center p-10">
        <i class="pi pi-spin pi-spinner text-2xl text-surface-400" />
    </div>

    <form v-else class="space-y-5" @submit.prevent="handleSubmit">
        <!-- Name -->
        <div class="flex flex-col gap-1.5">
            <label for="ac_name" class="text-base font-medium">
                {{ $t('sk-api-clients.fields.name') }}
                <span class="text-red-500">*</span>
            </label>
            <InputText
                id="ac_name"
                v-model="form.name"
                class="w-full"
                :invalid="!!firstError('name')"
                :disabled="loading"
            />
            <small v-if="firstError('name')" class="text-base text-red-500">{{ firstError('name') }}</small>
        </div>

        <!-- Grant Type (sadece create'de) -->
        <div v-if="!isEdit" class="flex flex-col gap-1.5">
            <label for="ac_grant_type" class="text-base font-medium">
                {{ $t('sk-api-clients.fields.grant_type') }}
                <span class="text-red-500">*</span>
            </label>
            <Select
                id="ac_grant_type"
                v-model="form.grant_type"
                :options="grantTypeOptions"
                option-label="label"
                option-value="value"
                class="w-full"
                :invalid="!!firstError('grant_type')"
                :disabled="loading"
            />
            <small v-if="firstError('grant_type')" class="text-base text-red-500">
                {{ firstError('grant_type') }}
            </small>
        </div>

        <!-- Redirect URIs (authorization_code için) -->
        <div v-if="form.grant_type === 'authorization_code'" class="flex flex-col gap-1.5">
            <label class="text-base font-medium">{{ $t('sk-api-clients.fields.redirect_uris') }}</label>
            <InputChips
                v-model="form.redirect_uris"
                :add-on-blur="true"
                separator=","
                class="w-full"
                :disabled="loading"
                :invalid="!!firstError('redirect_uris')"
            />
            <small class="text-base text-surface-400">{{ $t('sk-api-clients.fields.redirect_uris_hint') }}</small>
            <small v-if="firstError('redirect_uris')" class="text-base text-red-500">
                {{ firstError('redirect_uris') }}
            </small>
        </div>

        <!-- Scopes -->
        <div v-if="scopeOptions.length > 0" class="flex flex-col gap-1.5">
            <label for="ac_scopes" class="text-base font-medium">{{ $t('sk-api-clients.fields.scopes') }}</label>
            <MultiSelect
                id="ac_scopes"
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

        <!-- Genel hata özeti (field error'ları dışında kalan mesajlar için) -->
        <Message v-if="errors['_general']?.[0]" severity="error">
            {{ errors['_general'][0] }}
        </Message>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-2">
            <Button
                type="button"
                :label="$t('sk-button.cancel')"
                severity="secondary"
                outlined
                :disabled="loading"
                @click="handleCancel"
            />
            <Button
                type="submit"
                :label="isEdit ? $t('sk-button.save') : $t('sk-button.create')"
                icon="pi pi-check"
                :loading="loading"
            />
        </div>
    </form>
</template>
