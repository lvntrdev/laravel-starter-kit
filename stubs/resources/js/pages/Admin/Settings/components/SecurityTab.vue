<script setup lang="ts">
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import adminSettings from '@/routes/settings';
    import { useConfirm } from '@/composables/useConfirm';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        authSettings: {
            registration: boolean;
            email_verification: boolean;
            two_factor: boolean;
            password_reset: boolean;
        };
        turnstileSettings: {
            enabled: boolean;
            site_key: string | null;
            secret_key: null;
            secret_key_is_set: boolean;
        };
    }

    const props = defineProps<Props>();

    /* ── Authentication section ── */
    const { confirmAction } = useConfirm();
    const authFormRef = ref<InstanceType<typeof SkForm>>();

    const authFormConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(1)
            .cardTitle('sk-setting.auth.title')
            .cardSubtitle('sk-setting.auth.subtitle')
            .initialData(props.authSettings)
            .submit({
                url: adminSettings.update.auth.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                FB.toggleSwitch().key('registration').hint(trans('sk-setting.auth.registration_hint')),
                FB.toggleSwitch().key('email_verification').hint(trans('sk-setting.auth.email_verification_hint')),
                FB.toggleSwitch().key('two_factor').hint(trans('sk-setting.auth.two_factor_hint')),
                FB.toggleSwitch().key('password_reset').hint(trans('sk-setting.auth.password_reset_hint')),
            )
            .build(),
    );

    /**
     * Watch two_factor toggle — if user turns it OFF while it was ON,
     * show a warning that all users' 2FA will be revoked.
     */
    watch(
        () => authFormRef.value?.currentValues?.two_factor,
        (newVal, oldVal) => {
            if (oldVal === true && newVal === false) {
                confirmAction({
                    message: trans('sk-setting.auth.two_factor_disable_warning'),
                    header: trans('sk-setting.auth.two_factor_disable_title'),
                    icon: 'pi pi-exclamation-triangle',
                    acceptLabel: trans('sk-button.confirm'),
                    acceptClass: 'p-button-danger',
                    onAccept: () => {
                        // User confirmed — value stays false, they can submit normally
                    },
                    onReject: () => {
                        authFormRef.value?.setValue('two_factor', true);
                    },
                });
            }
        },
    );

    /* ── Cloudflare Turnstile section ── */
    const secretKeyPlaceholder = computed(() => (props.turnstileSettings.secret_key_is_set ? '••••••••' : ''));

    const turnstileFormConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(1)
            .cardTitle('sk-setting.turnstile.title')
            .cardSubtitle('sk-setting.turnstile.subtitle')
            .initialData({
                enabled: props.turnstileSettings.enabled,
                site_key: props.turnstileSettings.site_key ?? '',
                // Never prefill stored secret — backend preserves it when
                // this field is submitted empty.
                secret_key: '',
            })
            .submit({
                url: adminSettings.update.turnstile.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                FB.toggleSwitch().key('enabled').hint(trans('sk-setting.turnstile.enabled_hint')),
                FB.inputText().key('site_key').label('sk-setting.turnstile.site_key_label'),
                FB.password()
                    .key('secret_key')
                    .label('sk-setting.turnstile.secret_key_label')
                    .toggleMask()
                    .placeholder(secretKeyPlaceholder.value),
            )
            .build(),
    );
</script>

<template>
    <div class="space-y-6">
        <SkForm ref="authFormRef" :config="authFormConfig" />
        <SkForm :config="turnstileFormConfig" />
    </div>
</template>
