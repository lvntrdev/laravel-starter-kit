<script setup lang="ts">
    /**
     * Content language create / edit form.
     *
     * Content languages drive the locale tabs of translatable content fields
     * (TranslatableInput) — a separate concept from the admin UI locale, which
     * stays bound to lang files via config('app.languages'). The record itself
     * carries NO translatable fields (cyclic-dependency guard): name/native_name
     * are plain strings.
     *
     * The backend endpoints return ApiResponse (JSON) — not Inertia redirects —
     * so SkForm is used in external (v-model) mode and the submit/data fetch go
     * through useApi (same pattern as ApiClientForm).
     */
    import { computed, onMounted, ref } from 'vue';
    import { useApi } from '@/composables/useApi';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import adminSettings from '@/routes/settings';
    import { trans } from 'laravel-vue-i18n';
    import { usePage } from '@inertiajs/vue3';
    import { Button } from 'primevue';
    import { useToast } from 'primevue/usetoast';

    interface ContentLanguagePayload {
        id: string | number;
        code: string;
        name: string;
        native_name: string;
        direction: 'ltr' | 'rtl';
        flag: string | null;
        is_active: boolean;
        is_default: boolean;
        fallback_code: string | null;
        sort_order: number;
    }

    interface Props {
        languageId?: string | number | null;
        /** When true the language is the active default — its `is_default` toggle is locked on. */
        isDefault?: boolean;
        inDialog?: boolean;
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    const props = withDefaults(defineProps<Props>(), {
        languageId: null,
        isDefault: false,
        inDialog: false,
    });

    const emit = defineEmits<{
        success: [];
        cancel: [];
    }>();

    const api = useApi();
    const toast = useToast();
    const page = usePage();

    const isEdit = computed(() => props.languageId !== null && props.languageId !== undefined);
    const loading = ref(false);
    const dataLoading = ref(false);
    const errors = ref<Record<string, string[]>>({});

    const form = ref<Record<string, unknown>>({
        code: '',
        name: '',
        native_name: '',
        direction: 'ltr',
        flag: '',
        is_active: true,
        is_default: false,
        fallback_code: null,
        sort_order: 0,
    });

    const flatErrors = computed(() =>
        Object.fromEntries(
            Object.entries(errors.value).map(([k, v]) => [k, Array.isArray(v) ? (v[0] ?? '') : String(v)]),
        ),
    );

    /**
     * Direction options. Plain string select (ltr/rtl) — matches the
     * backend `Rule::in(['ltr', 'rtl'])` validation.
     */
    const directionOptions = computed(() => [
        { label: trans('sk-content-languages.directions.ltr'), value: 'ltr' },
        { label: trans('sk-content-languages.directions.rtl'), value: 'rtl' },
    ]);

    /**
     * Fallback options come from the currently active content locales the app
     * already exposes ({ code: name }). The record being edited is excluded so a
     * language can never fall back to itself (mirrors the backend `fallback_self`
     * guard). A leading "none" entry clears the fallback.
     */
    const fallbackOptions = computed(() => {
        const locales = (page.props.availableContentLocales ?? {}) as Record<string, string>;
        const selfCode = String(form.value.code ?? '');
        const options = Object.entries(locales)
            .filter(([code]) => code !== selfCode)
            .map(([code, name]) => ({ label: `${name} (${code})`, value: code }));

        return [{ label: trans('sk-content-languages.fields.fallback_none'), value: null }, ...options];
    });

    onMounted(async () => {
        if (!isEdit.value || props.languageId === null || props.languageId === undefined) return;

        dataLoading.value = true;
        try {
            const language = await api.get<ContentLanguagePayload>(
                adminSettings.contentLanguages.fetch.url(Number(props.languageId)),
            );

            form.value = {
                code: language.code ?? '',
                name: language.name ?? '',
                native_name: language.native_name ?? '',
                direction: language.direction ?? 'ltr',
                flag: language.flag ?? '',
                is_active: language.is_active ?? true,
                is_default: language.is_default ?? false,
                fallback_code: language.fallback_code ?? null,
                sort_order: language.sort_order ?? 0,
            };
        } catch {
            // useApi already surfaced an error toast; close the dialog.
            emit('cancel');
            props.onCancel?.();
        } finally {
            dataLoading.value = false;
        }
    });

    const formConfig = computed(() => {
        // A language that is currently the active default cannot be un-defaulted
        // from this form (at least one active default must always remain — backend
        // last_default guard). Lock the toggle on and disable it for that record.
        const lockDefault = props.isDefault;

        return FB.form()
            .layout('vertical')
            .cols(2)
            .inDialog(props.inDialog)
            .isCard(false)
            .addFields(
                FB.inputText()
                    .key('code')
                    .label('sk-content-languages.fields.code')
                    .hint(trans('sk-content-languages.fields.code_hint'))
                    .required(),
                FB.inputText().key('name').label('sk-content-languages.fields.name').required(),
                FB.inputText()
                    .key('native_name')
                    .label('sk-content-languages.fields.native_name')
                    .required(),
                FB.select()
                    .key('direction')
                    .label('sk-content-languages.fields.direction')
                    .options(directionOptions.value)
                    .default('ltr'),
                FB.inputText()
                    .key('flag')
                    .label('sk-content-languages.fields.flag')
                    .optional()
                    .hint(trans('sk-content-languages.fields.flag_hint')),
                FB.select()
                    .key('fallback_code')
                    .label('sk-content-languages.fields.fallback_code')
                    .options(fallbackOptions.value)
                    .filter(true)
                    .optional()
                    .hint(trans('sk-content-languages.fields.fallback_hint')),
                FB.inputNumber()
                    .key('sort_order')
                    .label('sk-content-languages.fields.sort_order')
                    .min(0)
                    .step(1)
                    .optional()
                    .default(0),
                FB.toggleSwitch()
                    .key('is_active')
                    .label('sk-content-languages.fields.is_active')
                    .description('sk-content-languages.fields.is_active_hint')
                    .default(true)
                    .colSpan(2),
                FB.toggleSwitch()
                    .key('is_default')
                    .label('sk-content-languages.fields.is_default')
                    .description('sk-content-languages.fields.is_default_hint')
                    .disabled(() => lockDefault)
                    .default(false)
                    .colSpan(2),
            )
            .build();
    });

    async function handleSubmit() {
        errors.value = {};
        loading.value = true;
        try {
            const payload = {
                code: form.value.code,
                name: form.value.name,
                native_name: form.value.native_name,
                direction: form.value.direction,
                flag: form.value.flag || null,
                is_active: form.value.is_active,
                is_default: form.value.is_default,
                fallback_code: form.value.fallback_code || null,
                sort_order: form.value.sort_order ?? 0,
            };

            if (isEdit.value) {
                await api.put(adminSettings.contentLanguages.save.url(Number(props.languageId)), payload);
                toast.add({
                    severity: 'success',
                    summary: trans('sk-content-languages.title'),
                    detail: trans('sk-message.updated', { entity: trans('sk-content-languages.entity') }),
                    group: 'bc',
                    life: 3000,
                });
            } else {
                await api.post(adminSettings.contentLanguages.add.url(), payload);
                toast.add({
                    severity: 'success',
                    summary: trans('sk-content-languages.title'),
                    detail: trans('sk-message.created', { entity: trans('sk-content-languages.entity') }),
                    group: 'bc',
                    life: 3000,
                });
            }

            emit('success');
            props.onSuccess?.();
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
