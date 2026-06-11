<script setup lang="ts">
    import { computed, ref, watch } from 'vue';
    import { Button } from 'primevue';
    import { FB } from '@lvntr/components/FormBuilder/core';
    import SkForm from '@lvntr/components/FormBuilder/SkForm.vue';
    import SkCard from '@lvntr/components/ui/SkCard.vue';
    import adminSettings from '@/routes/settings';
    import { useConfirm } from '@/composables/useConfirm';
    import { trans } from 'laravel-vue-i18n';

    interface Props {
        authSettings: {
            registration: boolean;
            email_verification: boolean;
            two_factor: boolean;
            password_reset: boolean;
            login_throttle: boolean;
            password_min_length: number;
            password_expiry_days: number;
            password_require_mixed_case: boolean;
            password_require_numbers: boolean;
            password_require_symbols: boolean;
        };
        turnstileSettings: {
            enabled: boolean;
            site_key: string | null;
            secret_key: null;
            secret_key_is_set: boolean;
        };
    }

    const props = defineProps<Props>();

    /* ── Horizontal sub-tab strip (Design Reference: 3 brand-underlined tabs) ── */
    type SecuritySection = 'auth' | 'password' | 'turnstile';

    const subTabs: Array<{ key: SecuritySection; label: string; icon: string }> = [
        { key: 'auth', label: 'sk-setting.security.subtabs.auth', icon: 'pi pi-shield' },
        { key: 'password', label: 'sk-setting.security.subtabs.password', icon: 'pi pi-lock' },
        { key: 'turnstile', label: 'sk-setting.security.subtabs.turnstile', icon: 'pi pi-cloud' },
    ];

    const activeSec = ref<SecuritySection>('auth');

    /* ── Authentication + Password Policy (single `update.auth` endpoint) ──
     *
     * Both sub-tabs feed ONE SkForm posting all ten auth.* fields to the same
     * endpoint. The two field groups are FB sections gated by `.visible()`;
     * because `authFormConfig` is a computed that reads `activeSec`, switching
     * sub-tabs re-evaluates section visibility without resetting the form's
     * internal state (the field set and initialData are unchanged, so SkForm
     * never re-derives defaults). The single footer submits everything at once.
     */
    const { confirmAction } = useConfirm();
    const authFormRef = ref<InstanceType<typeof SkForm>>();

    const authFormConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .isCard(false)
            .hideActions()
            .initialData(props.authSettings)
            .submit({
                url: adminSettings.update.auth.url(),
                method: 'put',
                preserveScroll: true,
            })
            .addFields(
                // ── Kimlik Doğrulama ──────────────────────────────────────────
                FB.section()
                    .isCard(false)
                    .cols(1)
                    .visible(() => activeSec.value === 'auth')
                    .addFields(
                        FB.toggleSwitch()
                            .key('registration')
                            .label('sk-setting.auth.registration_label')
                            .icon('pi pi-user-plus')
                            .description('sk-setting.auth.registration_hint'),
                        FB.toggleSwitch()
                            .key('password_reset')
                            .label('sk-setting.auth.password_reset_label')
                            .icon('pi pi-refresh')
                            .description('sk-setting.auth.password_reset_hint'),
                        FB.toggleSwitch()
                            .key('email_verification')
                            .label('sk-setting.auth.email_verification_label')
                            .icon('pi pi-envelope')
                            .description('sk-setting.auth.email_verification_hint'),
                        FB.toggleSwitch()
                            .key('two_factor')
                            .label('sk-setting.auth.two_factor_label')
                            .icon('pi pi-key')
                            .description('sk-setting.auth.two_factor_hint'),
                        FB.toggleSwitch()
                            .key('login_throttle')
                            .label('sk-setting.auth.login_throttle_label')
                            .icon('pi pi-shield')
                            .description('sk-setting.auth.login_throttle_hint'),
                    ),
                // ── Parola Politikası ─────────────────────────────────────────
                FB.section()
                    .isCard(false)
                    .cols(2)
                    .visible(() => activeSec.value === 'password')
                    .addFields(
                        FB.inputNumber()
                            .key('password_min_length')
                            .label('sk-setting.auth.password_min_length_label')
                            .icon('pi pi-hashtag')
                            .min(6)
                            .max(128)
                            .step(1)
                            .hint(trans('sk-setting.auth.password_min_length_hint')),
                        FB.inputNumber()
                            .key('password_expiry_days')
                            .label('sk-setting.auth.password_expiry_days_label')
                            .icon('pi pi-calendar-clock')
                            .min(0)
                            .max(3650)
                            .step(1)
                            .hint(trans('sk-setting.auth.password_expiry_days_hint')),
                        FB.toggleSwitch()
                            .key('password_require_mixed_case')
                            .label('sk-setting.auth.password_require_mixed_case_label')
                            .icon('Aa')
                            .description('sk-setting.auth.password_require_mixed_case_hint')
                            .colSpan(2),
                        FB.toggleSwitch()
                            .key('password_require_numbers')
                            .label('sk-setting.auth.password_require_numbers_label')
                            .icon('123')
                            .description('sk-setting.auth.password_require_numbers_hint')
                            .colSpan(2),
                        FB.toggleSwitch()
                            .key('password_require_symbols')
                            .label('sk-setting.auth.password_require_symbols_label')
                            .icon('!@#')
                            .description('sk-setting.auth.password_require_symbols_hint')
                            .colSpan(2),
                    ),
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
    const turnstileFormRef = ref<InstanceType<typeof SkForm>>();
    const secretKeyPlaceholder = computed(() => (props.turnstileSettings.secret_key_is_set ? '••••••••' : ''));

    const turnstileFormConfig = computed(() =>
        FB.form()
            .layout('vertical')
            .cols(2)
            .isCard(false)
            .hideActions()
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
                FB.toggleSwitch()
                    .key('enabled')
                    .label('sk-setting.turnstile.enabled_label')
                    .icon('pi pi-cloud')
                    .description('sk-setting.turnstile.enabled_hint')
                    .colSpan(2),
                FB.inputText().key('site_key').label('sk-setting.turnstile.site_key_label'),
                FB.password()
                    .key('secret_key')
                    .label('sk-setting.turnstile.secret_key_label')
                    .toggleMask()
                    .placeholder(secretKeyPlaceholder.value),
            )
            .build(),
    );

    /* ── Card-integrated footer ──
     * One footer drives whichever form the active sub-tab shows: auth + password
     * share `authFormRef` (single `update.auth` endpoint); Turnstile is its own
     * form/endpoint. The footer reads the active form's reactive `isDirty` /
     * `processing` mirrors and calls the exposed `submit()`.
     */
    const activeForm = computed(() => (activeSec.value === 'turnstile' ? turnstileFormRef.value : authFormRef.value));
</script>

<template>
    <!-- Card's own title system carries the heading; tab strip + forms live in the body -->
    <SkCard :title="$t('sk-setting.security.title')" :subtitle="$t('sk-setting.security.subtitle')">
        <!-- Horizontal sub-tab strip at the top of the body (flush, own baseline) -->
        <nav class="-mx-6 -mt-6 mb-6 flex border-b border-surface-200 dark:border-surface-700" role="tablist">
            <button
                v-for="(tab, i) in subTabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="activeSec === tab.key"
                class="-mb-px inline-flex h-14 items-center gap-2 border-b-[3px] text-sm font-semibold transition-colors"
                :class="[
                    i === 0 ? 'pl-6 pr-5' : 'px-5',
                    activeSec === tab.key
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                        : 'border-transparent text-surface-500 hover:text-surface-700 dark:text-surface-400 dark:hover:text-surface-200',
                ]"
                @click="activeSec = tab.key"
            >
                <i
                    :class="[tab.icon, activeSec === tab.key ? 'text-primary-500 dark:text-primary-400' : 'text-surface-400']"
                    class="text-base"
                />
                {{ $t(tab.label) }}
            </button>
        </nav>

        <!-- Body: auth + password share one form (single endpoint); Turnstile its own -->
        <SkForm v-show="activeSec !== 'turnstile'" ref="authFormRef" :config="authFormConfig" />
        <SkForm v-show="activeSec === 'turnstile'" ref="turnstileFormRef" :config="turnstileFormConfig" />

        <!-- Edge-to-edge card footer drives the active form -->
        <template #footer>
            <!-- Always present so its `mr-auto` keeps the buttons right-aligned -->
            <span class="sk-card__foot-hint">{{ activeForm?.isDirty ? $t('sk-setting.security.unsaved') : '' }}</span>
            <Button
                :label="$t('sk-button.update')"
                icon="pi pi-save"
                severity="primary"
                :loading="activeForm?.processing"
                :disabled="!activeForm?.isDirty"
                @click="activeForm?.submit()"
            />
        </template>
    </SkCard>
</template>
