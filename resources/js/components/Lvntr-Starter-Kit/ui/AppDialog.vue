<!-- resources/js/components/AppDialog.vue -->
<!-- Global dialog — registered once in AdminLayout.                         -->
<!-- Material Flat shell: gradient icon lozenge + title/subtitle + body +    -->
<!-- optional slate-100 footer with cancel/confirm actions.                  -->
<!-- Renders whatever component is passed via useDialog().open().            -->
<script setup lang="ts">
    import { useDialog } from '@/composables/useDialog';
    import { trans } from 'laravel-vue-i18n';
    import Button from 'primevue/button';

    const { state, close } = useDialog();

    const confirmBusy = ref(false);

    // Çift tık koruması: kartı çift tıklayan kullanıcının ikinci tıkı, yeni
    // mount olan mask'e düşüyor ve PrimeVue dismissableMask bunu backdrop tıkı
    // sanıp modalı anında kapatıyordu. Açılıştan sonraki kısa pencerede mask
    // dismissal'ı kapalı tutulur (çift tık aralığı + açılış animasyonu ~320ms).
    const maskDismissable = ref(false);
    let maskGuardTimer: ReturnType<typeof setTimeout> | null = null;

    watch(
        () => state.visible,
        (visible) => {
            if (maskGuardTimer !== null) {
                clearTimeout(maskGuardTimer);
                maskGuardTimer = null;
            }
            if (visible) {
                maskDismissable.value = false;
                maskGuardTimer = setTimeout(() => {
                    maskDismissable.value = true;
                    maskGuardTimer = null;
                }, 400);
            }
        },
    );

    const cancelLabel = computed(() => state.footer?.cancelLabel ?? trans('sk-button.cancel'));
    const confirmLabel = computed(() => state.footer?.confirmLabel ?? trans('sk-button.confirm'));
    const confirmIcon = computed(() => state.footer?.confirmIcon ?? 'pi pi-check');
    const confirmSeverity = computed(() => state.footer?.severity);

    async function handleConfirm(): Promise<void> {
        const fn = state.footer?.onConfirm;
        if (!fn || state.footer?.disabled || state.footer?.loading || confirmBusy.value) return;

        try {
            confirmBusy.value = true;
            await fn();
        } finally {
            confirmBusy.value = false;
        }
    }
</script>

<template>
    <Dialog
        :visible="state.visible"
        :modal="true"
        :draggable="false"
        :dismissable-mask="maskDismissable"
        :close-on-escape="true"
        :show-header="false"
        :style="{ width: state.width }"
        :breakpoints="{ '768px': '95vw' }"
        :pt="{
            root: { class: 'sk-dlg' },
            mask: { class: ['sk-dlg__mask', { 'sk-dlg__mask--dark': state.darkMask }] },
        }"
        @update:visible="(val) => !val && close()"
    >
        <template #container="{ closeCallback }">
            <div class="sk-dlg__shell">
                <!-- Header -->
                <header class="sk-dlg__head">
                    <div class="sk-dlg__title-group">
                        <div v-if="state.icon" class="sk-dlg__lead" aria-hidden="true">
                            <i :class="state.icon" />
                        </div>
                        <div class="sk-dlg__title-block">
                            <h2 class="sk-dlg__title">
                                {{ state.header }}
                            </h2>
                            <p v-if="state.subtitle" class="sk-dlg__subtitle">
                                {{ state.subtitle }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="sk-dlg__close"
                        :aria-label="trans('sk-button.close')"
                        @click="closeCallback"
                    >
                        <i class="pi pi-times" />
                    </button>
                </header>

                <!-- Body -->
                <div class="sk-dlg__body" :class="{ 'sk-dlg__body--with-footer': state.footer }">
                    <div v-if="state.loading" class="sk-dlg__loader">
                        <i class="pi pi-spin pi-spinner" />
                    </div>

                    <component
                        :is="state.component"
                        v-else-if="state.component"
                        v-bind="state.props"
                    />
                </div>

                <!-- Footer (opt-in via state.footer) -->
                <footer v-if="state.footer" class="sk-dlg__foot">
                    <!-- Far-left slot -->
                    <component
                        :is="state.footer.startSlot"
                        v-if="state.footer.startSlot"
                        v-bind="state.footer.startSlotProps"
                        class="sk-dlg__slot sk-dlg__slot--start"
                    />

                    <!-- Built-in hint (icon + text) -->
                    <div v-if="state.footer.icon || state.footer.text" class="sk-dlg__info">
                        <i
                            v-if="state.footer.icon"
                            class="sk-dlg__info-icon"
                            :class="state.footer.icon"
                            aria-hidden="true"
                        />
                        <span v-if="state.footer.text">{{ state.footer.text }}</span>
                    </div>

                    <span class="sk-dlg__foot-spacer" />

                    <!-- Slot just before the action buttons -->
                    <component
                        :is="state.footer.endSlot"
                        v-if="state.footer.endSlot"
                        v-bind="state.footer.endSlotProps"
                        class="sk-dlg__slot sk-dlg__slot--end"
                    />

                    <div class="sk-dlg__actions">
                        <Button
                            v-if="!state.footer.hideCancel"
                            :label="cancelLabel"
                            severity="secondary"
                            text
                            @click="closeCallback"
                        />
                        <Button
                            v-if="!state.footer.hideConfirm"
                            :label="confirmLabel"
                            :icon="confirmIcon"
                            :severity="confirmSeverity"
                            :disabled="state.footer.disabled"
                            :loading="state.footer.loading || confirmBusy"
                            @click="handleConfirm"
                        />
                    </div>
                </footer>
            </div>
        </template>
    </Dialog>
</template>
