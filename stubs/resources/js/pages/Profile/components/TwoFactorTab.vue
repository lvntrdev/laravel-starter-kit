<script setup lang="ts">
    import { useForm, router } from '@inertiajs/vue3';
    import { getActiveLanguage } from 'laravel-vue-i18n';
    import axios, { type AxiosError } from 'axios';
    import SkCard from '@lvntr/components/ui/SkCard.vue';

    interface Props {
        twoFactorEnabled: boolean;
        twoFactorConfirmed: boolean;
        twoFactorConfirmedAt?: string | null;
    }

    const props = defineProps<Props>();

    // Fortify always issues a fixed-size batch of recovery codes.
    const RECOVERY_TOTAL = 8;

    const twoFactorProcessing = ref(false);
    const qrCodeSvg = ref('');
    const setupKey = ref('');
    const recoveryCodes = ref<string[]>([]);
    const showRecoveryCodes = ref(false);
    const setupKeyCopied = ref(false);
    const recoveryCopied = ref(false);

    // Activation date badge (e.g. "12 Haz 2026"), localised to the active app locale.
    const confirmedAtLabel = computed<string>(() => {
        if (!props.twoFactorConfirmedAt) return '';
        const date = new Date(props.twoFactorConfirmedAt);
        if (Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat(getActiveLanguage(), {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        }).format(date);
    });

    // Real codes once loaded; otherwise masked placeholders so the grid keeps its shape.
    const gridCodes = computed<string[]>(() =>
        recoveryCodes.value.length
            ? recoveryCodes.value
            : Array.from({ length: RECOVERY_TOTAL }, () => '••••-••••'),
    );

    async function copySetupKey() {
        if (!setupKey.value) return;
        try {
            await navigator.clipboard.writeText(setupKey.value);
            setupKeyCopied.value = true;
            setTimeout(() => (setupKeyCopied.value = false), 1500);
        } catch {
            // Clipboard unavailable (insecure context) — silently ignore.
        }
    }

    async function copyRecoveryCodes() {
        if (!recoveryCodes.value.length) return;
        try {
            await navigator.clipboard.writeText(recoveryCodes.value.join('\n'));
            recoveryCopied.value = true;
            setTimeout(() => (recoveryCopied.value = false), 1500);
        } catch {
            // Clipboard unavailable (insecure context) — silently ignore.
        }
    }

    function downloadRecoveryCodes() {
        if (!recoveryCodes.value.length) return;
        const blob = new Blob([recoveryCodes.value.join('\n')], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'recovery-codes.txt';
        link.click();
        URL.revokeObjectURL(url);
    }

    /**
     * Render the Fortify QR SVG through an <img src="data:..."> element
     * rather than v-html. An <img> sandbox neutralises any inline <script>
     * or event handlers that a compromised intermediary could smuggle in.
     */
    const qrCodeDataUrl = computed<string>(() => {
        if (!qrCodeSvg.value) return '';
        try {
            const encoded = window.btoa(unescape(encodeURIComponent(qrCodeSvg.value)));
            return `data:image/svg+xml;base64,${encoded}`;
        } catch {
            return '';
        }
    });

    const confirmForm = useForm({
        code: '',
    });

    // ── Password Confirmation Dialog ─────────────────────────────────────────────

    const showPasswordDialog = ref(false);
    const passwordConfirmError = ref('');
    const passwordConfirmProcessing = ref(false);
    type PendingAction = 'enable' | 'disable' | 'show-codes' | 'regenerate-codes';
    const pendingAction = ref<PendingAction | null>(null);

    const passwordConfirmForm = useForm({
        password: '',
    });

    function requirePasswordConfirmation(action: PendingAction) {
        pendingAction.value = action;
        passwordConfirmForm.reset();
        passwordConfirmError.value = '';
        showPasswordDialog.value = true;
    }

    async function submitPasswordConfirmation() {
        passwordConfirmProcessing.value = true;
        passwordConfirmError.value = '';

        try {
            await axios.post('/user/confirm-password', {
                password: passwordConfirmForm.password,
            });

            showPasswordDialog.value = false;

            if (pendingAction.value === 'enable') {
                await enableTwoFactor();
            } else if (pendingAction.value === 'disable') {
                await disableTwoFactor();
            } else if (pendingAction.value === 'show-codes') {
                await fetchRecoveryCodes();
                showRecoveryCodes.value = true;
            } else if (pendingAction.value === 'regenerate-codes') {
                await regenerateRecoveryCodes();
                showRecoveryCodes.value = true;
            }
        } catch (err) {
            const error = err as AxiosError<{ errors?: { password?: string[] } }>;
            if (error.response?.status === 422) {
                passwordConfirmError.value = error.response.data?.errors?.password?.[0] ?? 'The password is incorrect.';
            }
        } finally {
            passwordConfirmProcessing.value = false;
            pendingAction.value = null;
        }
    }

    async function enableTwoFactor() {
        twoFactorProcessing.value = true;

        try {
            if (!props.twoFactorEnabled) {
                await axios.post('/user/two-factor-authentication');
                // Wait for the new props to land before hitting the QR endpoint
                // — otherwise Fortify may still report "not enabled" when we ask
                // for the QR / secret key.
                await new Promise<void>((resolve) => {
                    router.reload({
                        only: ['twoFactorEnabled', 'twoFactorConfirmed'],
                        onFinish: () => resolve(),
                    });
                });
            }

            await loadQrAndSetupKey();
        } finally {
            twoFactorProcessing.value = false;
        }
    }

    async function loadQrAndSetupKey() {
        const [qrResponse, keyResponse] = await Promise.all([
            axios.get('/user/two-factor-qr-code'),
            axios.get('/user/two-factor-secret-key'),
        ]);
        qrCodeSvg.value = qrResponse.data.svg;
        setupKey.value = keyResponse.data.secretKey;
    }

    async function confirmTwoFactor() {
        confirmForm.post('/user/confirmed-two-factor-authentication', {
            preserveScroll: true,
            onSuccess: async () => {
                confirmForm.reset();
                qrCodeSvg.value = '';
                setupKey.value = '';
                await fetchRecoveryCodes();
                showRecoveryCodes.value = true;
                router.reload({ only: ['twoFactorEnabled', 'twoFactorConfirmed'] });
            },
        });
    }

    async function disableTwoFactor() {
        twoFactorProcessing.value = true;

        try {
            await axios.delete('/user/two-factor-authentication');

            qrCodeSvg.value = '';
            setupKey.value = '';
            showRecoveryCodes.value = false;
            recoveryCodes.value = [];

            router.reload({ only: ['twoFactorEnabled', 'twoFactorConfirmed'] });
        } finally {
            twoFactorProcessing.value = false;
        }
    }

    async function fetchRecoveryCodes() {
        const response = await axios.get('/user/two-factor-recovery-codes');
        recoveryCodes.value = response.data;
    }

    async function regenerateRecoveryCodes() {
        await axios.post('/user/two-factor-recovery-codes');
        await fetchRecoveryCodes();
    }

    async function showExistingRecoveryCodes() {
        if (showRecoveryCodes.value) {
            showRecoveryCodes.value = false;
            return;
        }
        requirePasswordConfirmation('show-codes');
    }

    onMounted(async () => {
        if (props.twoFactorEnabled && !props.twoFactorConfirmed) {
            try {
                await loadQrAndSetupKey();
            } catch {
                // Password confirmation may have expired
            }
        }
    });
</script>

<template>
    <div>
        <SkCard>
            <template #title>
                {{ $t('sk-profile.two_factor_title') }}
            </template>
            <template #subtitle>
                {{ $t('sk-profile.two_factor_subtitle') }}
            </template>
            <template #content>
                <div class="space-y-4">
                    <!-- Status: Enabled & Confirmed -->
                    <div v-if="props.twoFactorConfirmed">
                        <!-- Hero -->
                        <div class="flex gap-4">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30"
                            >
                                <i class="pi pi-check-circle text-xl text-green-600 dark:text-green-400" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <h3 class="text-base font-bold text-surface-900 dark:text-surface-100">
                                    {{ $t('sk-profile.two_factor_on_title') }}
                                </h3>
                                <p class="text-sm leading-relaxed text-surface-500 dark:text-surface-400">
                                    {{ $t('sk-profile.two_factor_on_desc') }}
                                </p>
                                <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                    >
                                        <i class="pi pi-check text-[10px]" />
                                        {{ $t('sk-profile.two_factor_badge_on') }}
                                    </span>
                                    <span
                                        v-if="confirmedAtLabel"
                                        class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-xs font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        <i class="pi pi-calendar text-[10px]" />
                                        {{ $t('sk-profile.two_factor_enabled_at', { date: confirmedAtLabel }) }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-xs font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        <i class="pi pi-mobile text-[10px]" />
                                        {{ $t('sk-profile.two_factor_badge_app') }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Recovery codes card -->
                        <div class="mt-5 rounded-lg border border-surface-200 dark:border-surface-700">
                            <!-- Header -->
                            <div class="flex flex-wrap items-start justify-between gap-3 p-4">
                                <div class="flex gap-3">
                                    <div
                                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-surface-100 dark:bg-surface-800"
                                    >
                                        <i class="pi pi-key text-sm text-primary-600 dark:text-primary-300" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                            {{ $t('sk-profile.two_factor_recovery_codes_title') }}
                                        </p>
                                        <p class="mt-0.5 text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                                            {{ $t('sk-profile.two_factor_recovery_codes_desc') }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button
                                        :label="
                                            showRecoveryCodes
                                                ? $t('sk-profile.two_factor_hide')
                                                : $t('sk-profile.two_factor_show')
                                        "
                                        :icon="showRecoveryCodes ? 'pi pi-eye-slash' : 'pi pi-eye'"
                                        severity="secondary"
                                        size="small"
                                        @click="showExistingRecoveryCodes"
                                    />
                                    <Button
                                        :label="
                                            recoveryCopied ? $t('sk-common.copied') : $t('sk-common.copy')
                                        "
                                        :icon="recoveryCopied ? 'pi pi-check' : 'pi pi-copy'"
                                        severity="secondary"
                                        size="small"
                                        :disabled="!showRecoveryCodes || !recoveryCodes.length"
                                        @click="copyRecoveryCodes"
                                    />
                                    <Button
                                        :label="$t('sk-profile.two_factor_download')"
                                        icon="pi pi-download"
                                        severity="secondary"
                                        size="small"
                                        :disabled="!showRecoveryCodes || !recoveryCodes.length"
                                        @click="downloadRecoveryCodes"
                                    />
                                </div>
                            </div>

                            <!-- Codes grid -->
                            <div class="grid grid-cols-2 gap-2.5 px-4 pb-4 sm:grid-cols-4">
                                <code
                                    v-for="(code, i) in gridCodes"
                                    :key="i"
                                    class="rounded-md border border-surface-200 bg-surface-50 px-3 py-2 text-center font-mono text-sm tracking-wider text-surface-700 transition dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-200"
                                    :class="{ 'select-none blur-sm': !showRecoveryCodes }"
                                >
                                    {{ code }}
                                </code>
                            </div>

                            <!-- Footer -->
                            <div
                                class="flex flex-wrap items-center justify-between gap-3 border-t border-surface-200 px-4 py-3 dark:border-surface-700"
                            >
                                <span class="flex items-center gap-1.5 text-xs text-surface-500 dark:text-surface-400">
                                    <i class="pi pi-info-circle" />
                                    {{ $t('sk-profile.two_factor_recovery_once') }}
                                    <template v-if="recoveryCodes.length">
                                        ·
                                        <span class="font-semibold text-surface-700 dark:text-surface-200">{{
                                            recoveryCodes.length
                                        }}</span
                                        >/{{ RECOVERY_TOTAL }} {{ $t('sk-profile.two_factor_recovery_usable') }}
                                    </template>
                                </span>
                                <Button
                                    :label="$t('sk-profile.two_factor_regenerate_short')"
                                    icon="pi pi-refresh"
                                    severity="secondary"
                                    size="small"
                                    text
                                    @click="requirePasswordConfirmation('regenerate-codes')"
                                />
                            </div>
                        </div>

                        <!-- Full-bleed disable footer -->
                        <div
                            class="-mx-6 -mb-[22px] mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-surface-200 px-6 py-[14px] dark:border-surface-700"
                        >
                            <span class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
                                <i class="pi pi-info-circle" />
                                {{ $t('sk-profile.two_factor_disable_warning') }}
                            </span>
                            <Button
                                :label="$t('sk-profile.two_factor_disable')"
                                icon="pi pi-times"
                                severity="danger"
                                :loading="twoFactorProcessing"
                                @click="requirePasswordConfirmation('disable')"
                            />
                        </div>
                    </div>

                    <!-- Status: Not Enabled — landing content (SkCard is the frame) -->
                    <div v-if="!props.twoFactorEnabled">
                        <div class="flex gap-4">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-900/30"
                            >
                                <i class="pi pi-shield text-xl text-primary-600 dark:text-primary-300" />
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <h3 class="text-base font-bold text-surface-900 dark:text-surface-100">
                                    {{ $t('sk-profile.two_factor_hero_title') }}
                                </h3>
                                <p class="text-sm leading-relaxed text-surface-500 dark:text-surface-400">
                                    {{ $t('sk-profile.two_factor_hero_desc') }}
                                </p>
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-xs font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                >
                                    <span class="size-1.5 rounded-full bg-surface-400" />
                                    {{ $t('sk-profile.two_factor_status_off') }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-lg border border-surface-200 p-3 dark:border-surface-700">
                                <div class="flex items-start gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md bg-surface-100 dark:bg-surface-800"
                                    >
                                        <i class="pi pi-mobile text-sm text-primary-600 dark:text-primary-300" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                            {{ $t('sk-profile.two_factor_feature_app_title') }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                            {{ $t('sk-profile.two_factor_feature_app_desc') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-surface-200 p-3 dark:border-surface-700">
                                <div class="flex items-start gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md bg-surface-100 dark:bg-surface-800"
                                    >
                                        <i class="pi pi-lock text-sm text-primary-600 dark:text-primary-300" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                            {{ $t('sk-profile.two_factor_feature_phish_title') }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                            {{ $t('sk-profile.two_factor_feature_phish_desc') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-surface-200 p-3 dark:border-surface-700">
                                <div class="flex items-start gap-2.5">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-md bg-surface-100 dark:bg-surface-800"
                                    >
                                        <i class="pi pi-key text-sm text-primary-600 dark:text-primary-300" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                            {{ $t('sk-profile.two_factor_feature_recovery_title') }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                            {{ $t('sk-profile.two_factor_feature_recovery_desc') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Full-bleed footer: -mx-6 / -mb-[22px] cancel SkCard body padding -->
                        <div
                            class="-mx-6 -mb-[22px] mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-surface-200 px-6 py-[14px] dark:border-surface-700"
                        >
                            <span class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
                                <i class="pi pi-clock" />
                                {{ $t('sk-profile.two_factor_setup_time') }}
                            </span>
                            <Button
                                :label="$t('sk-profile.two_factor_activate')"
                                icon="pi pi-shield"
                                :loading="twoFactorProcessing"
                                @click="requirePasswordConfirmation('enable')"
                            />
                        </div>
                    </div>

                    <!-- QR Code Setup (enabled but not confirmed OR just enabled) -->
                    <template v-if="props.twoFactorEnabled && !props.twoFactorConfirmed">
                        <div
                            class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
                        >
                            <i class="pi pi-exclamation-triangle mt-0.5" />
                            {{ $t('sk-profile.two_factor_finish') }}
                        </div>

                        <!-- QR loaded — two-column setup -->
                        <form v-if="qrCodeSvg" @submit.prevent="confirmTwoFactor">
                            <div class="grid gap-6 sm:grid-cols-[auto_1fr]">
                                <!-- QR code -->
                                <div
                                    class="flex size-fit shrink-0 items-center justify-center rounded-lg border border-surface-200 bg-white p-3 dark:border-surface-700"
                                >
                                    <img
                                        v-if="qrCodeDataUrl"
                                        :src="qrCodeDataUrl"
                                        :alt="$t('sk-profile.two_factor_step1_title')"
                                        class="size-44"
                                    >
                                </div>

                                <!-- Numbered steps -->
                                <div class="space-y-5">
                                    <!-- Step 1 -->
                                    <div class="flex gap-3">
                                        <span
                                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300"
                                        >
                                            1
                                        </span>
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                                {{ $t('sk-profile.two_factor_step1_title') }}
                                            </p>
                                            <p class="text-sm text-surface-500 dark:text-surface-400">
                                                {{ $t('sk-profile.two_factor_step1_desc') }}
                                            </p>
                                            <div
                                                v-if="setupKey"
                                                class="flex items-stretch overflow-hidden rounded-md border border-surface-200 dark:border-surface-700"
                                            >
                                                <code
                                                    class="flex-1 bg-surface-50 px-3 py-2 font-mono text-sm tracking-wider text-surface-700 dark:bg-surface-800 dark:text-surface-200"
                                                >
                                                    {{ setupKey }}
                                                </code>
                                                <button
                                                    type="button"
                                                    class="flex items-center border-l border-surface-200 px-3 text-surface-500 transition-colors hover:bg-surface-50 hover:text-surface-700 dark:border-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                                                    :aria-label="$t('sk-common.copy')"
                                                    @click="copySetupKey"
                                                >
                                                    <i :class="setupKeyCopied ? 'pi pi-check text-green-500' : 'pi pi-copy'" />
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Step 2 -->
                                    <div class="flex gap-3">
                                        <span
                                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300"
                                        >
                                            2
                                        </span>
                                        <div class="min-w-0 flex-1 space-y-2">
                                            <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                                {{ $t('sk-profile.two_factor_step2_title') }}
                                            </p>
                                            <p class="text-sm text-surface-500 dark:text-surface-400">
                                                {{ $t('sk-profile.two_factor_step2_desc') }}
                                            </p>
                                            <InputOtp
                                                v-model="confirmForm.code"
                                                :length="6"
                                                integer-only
                                                :invalid="!!confirmForm.errors.code"
                                            />
                                            <small v-if="confirmForm.errors.code" class="block text-red-500">
                                                {{ confirmForm.errors.code }}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Full-bleed footer -->
                            <div
                                class="-mx-6 -mb-[22px] mt-6 flex flex-wrap items-center justify-between gap-4 border-t border-surface-200 px-6 py-[14px] dark:border-surface-700"
                            >
                                <span class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
                                    <i class="pi pi-shield" />
                                    {{ $t('sk-profile.two_factor_app_required') }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <Button
                                        type="button"
                                        :label="$t('sk-profile.two_factor_cancel_setup')"
                                        severity="secondary"
                                        text
                                        :loading="twoFactorProcessing"
                                        @click="requirePasswordConfirmation('disable')"
                                    />
                                    <Button
                                        type="submit"
                                        :label="$t('sk-profile.two_factor_verify')"
                                        icon="pi pi-check"
                                        :loading="confirmForm.processing"
                                    />
                                </div>
                            </div>
                        </form>

                        <!-- QR not loaded yet (password confirmation expired) -->
                        <div v-else class="space-y-3">
                            <p class="text-sm text-surface-500 dark:text-surface-400">
                                {{ $t('sk-profile.two_factor_expired') }}
                            </p>
                            <div class="flex items-center gap-2">
                                <Button
                                    :label="$t('sk-profile.two_factor_continue')"
                                    icon="pi pi-refresh"
                                    @click="requirePasswordConfirmation('enable')"
                                />
                                <Button
                                    :label="$t('sk-profile.two_factor_cancel_setup')"
                                    icon="pi pi-times"
                                    severity="secondary"
                                    :loading="twoFactorProcessing"
                                    @click="requirePasswordConfirmation('disable')"
                                />
                            </div>
                        </div>
                    </template>

                </div>
            </template>
        </SkCard>

        <!-- Password Confirmation Dialog -->
        <Dialog
            v-model:visible="showPasswordDialog"
            :header="$t('sk-profile.confirm_password_title')"
            modal
            :style="{ width: '25rem' }"
        >
            <p class="mb-4 text-sm text-surface-500 dark:text-surface-400">
                {{ $t('sk-profile.confirm_password_message') }}
            </p>

            <form @submit.prevent="submitPasswordConfirmation">
                <div class="flex flex-col gap-1">
                    <label
                        for="confirm_password_dialog"
                        class="text-sm font-medium text-surface-700 dark:text-surface-300"
                    >
                        {{ $t('sk-common.password') }}
                    </label>
                    <Password
                        v-model="passwordConfirmForm.password"
                        input-id="confirm_password_dialog"
                        :invalid="!!passwordConfirmError"
                        :feedback="false"
                        autocomplete="current-password"
                        toggle-mask
                        fluid
                        autofocus
                    />
                    <small v-if="passwordConfirmError" class="text-red-500">
                        {{ passwordConfirmError }}
                    </small>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <Button
                        type="button"
                        :label="$t('sk-button.cancel')"
                        severity="secondary"
                        @click="showPasswordDialog = false"
                    />
                    <Button
                        type="submit"
                        :label="$t('sk-button.confirm')"
                        icon="pi pi-lock"
                        :loading="passwordConfirmProcessing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>
