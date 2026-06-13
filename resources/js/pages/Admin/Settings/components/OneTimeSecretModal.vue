<script setup lang="ts">
    import { Button } from 'primevue';
    import { trans } from 'laravel-vue-i18n';
    import { useToast } from 'primevue/usetoast';

    /**
     * Tek sefer gizli anahtar gösterme içeriği.
     *
     * useDialog() ile açılır — Dialog wrapper bu component'ta değil,
     * global AppDialog'da yönetilir.
     *
     * Güvenlik notu: Kullanıcı "Sakladım" butonuna basmadan dialog
     * onSuccess callback'i tetiklenmez; tablo yenilemesi yapılmaz.
     */

    interface Props {
        secret: string;
        type?: 'secret' | 'token';
        /** useDialog() refreshKey ile otomatik inject edilir */
        onSuccess?: () => void;
    }

    const props = withDefaults(defineProps<Props>(), {
        type: 'secret',
    });

    const toast = useToast();
    const copied = ref(false);

    const descriptionKey = computed(() =>
        props.type === 'token' ? 'sk-api-tokens.token_modal.description' : 'sk-api-clients.secret_modal.description',
    );
    const copyKey = computed(() =>
        props.type === 'token' ? 'sk-api-tokens.token_modal.copy' : 'sk-api-clients.secret_modal.copy',
    );
    const copiedKey = computed(() =>
        props.type === 'token' ? 'sk-api-tokens.token_modal.copied' : 'sk-api-clients.secret_modal.copied',
    );
    const confirmKey = computed(() =>
        props.type === 'token' ? 'sk-api-tokens.token_modal.confirm' : 'sk-api-clients.secret_modal.confirm',
    );

    async function copyToClipboard() {
        try {
            await navigator.clipboard.writeText(props.secret);
            copied.value = true;
            toast.add({
                severity: 'success',
                summary: trans(copiedKey.value),
                group: 'bc',
                life: 2000,
            });
            setTimeout(() => (copied.value = false), 3000);
        } catch {
            // Clipboard API başarısız olursa kullanıcı manuel kopyalayabilir
        }
    }

    function handleConfirm() {
        props.onSuccess?.();
    }
</script>

<template>
    <!-- Uyarı banner -->
    <div
        class="mb-5 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/50 dark:bg-amber-900/20"
    >
        <i class="pi pi-exclamation-triangle mt-0.5 text-amber-600 dark:text-amber-400" />
        <p class="text-base text-amber-800 dark:text-amber-300">
            {{ $t(descriptionKey) }}
        </p>
    </div>

    <!-- Secret değeri -->
    <div class="mb-5">
        <div
            class="flex items-center gap-2 rounded-lg border border-surface-200 bg-surface-100 p-3 dark:border-surface-700 dark:bg-surface-800"
        >
            <code class="flex-1 break-all font-mono text-base text-surface-800 select-all dark:text-surface-100">
                {{ secret }}
            </code>
            <Button
                :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
                :severity="copied ? 'success' : 'secondary'"
                text
                rounded
                size="small"
                :aria-label="$t(copyKey)"
                @click="copyToClipboard"
            />
        </div>
    </div>

    <!-- Kopyala butonu (prominent) -->
    <Button
        :label="copied ? $t(copiedKey) : $t(copyKey)"
        :icon="copied ? 'pi pi-check' : 'pi pi-copy'"
        :severity="copied ? 'success' : 'primary'"
        class="mb-5 w-full"
        @click="copyToClipboard"
    />

    <!-- Sakladım butonu -->
    <Button
        :label="$t(confirmKey)"
        icon="pi pi-lock"
        severity="contrast"
        class="w-full"
        @click="handleConfirm"
    />
</template>
