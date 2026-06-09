<script setup lang="ts">
    // The kit's toast renders through a custom #container template, so PrimeVue's
    // native severity markup does not apply — the look is driven entirely by the
    // .sk-toast* classes in the theme (components/toast.css). A single `--toast-c`
    // accent, resolved per severity from the `sk-toast-<severity>` class, paints
    // every part (icon ring, summary, progress bar, action buttons).
    //
    // toast.add() accepts arbitrary extra fields; the template reads a few:
    //   icon       — pi icon class; falls back to a per-severity default
    //   styleClass — opt into a variant: 'sk-toast-solid' | 'sk-toast-outlined'
    //   actions    — pill buttons: [{ label, command?, primary?, dismiss? }]
    // Any Tailwind family name works as a severity too (e.g. severity: 'indigo').

    interface ToastAction {
        label: string;
        command?: () => void;
        primary?: boolean;
        // Close the toast after running the command (default true).
        dismiss?: boolean;
    }

    // The container slot types `message` as `any`, so no casts are needed.
    const severityIcons: Record<string, string> = {
        success: 'pi pi-check-circle',
        info: 'pi pi-info-circle',
        warn: 'pi pi-exclamation-triangle',
        error: 'pi pi-times-circle',
        secondary: 'pi pi-bookmark',
        contrast: 'pi pi-bell',
    };

    const iconFor = (message: { icon?: string; severity?: string }): string =>
        message.icon ?? severityIcons[message.severity ?? ''] ?? 'pi pi-bell';

    const actionsOf = (message: { actions?: ToastAction[] }): ToastAction[] => message.actions ?? [];

    const runAction = (action: ToastAction, close: () => void): void => {
        action.command?.();
        if (action.dismiss !== false) {
            close();
        }
    };
</script>

<template>
    <Toast position="bottom-center" group="bc">
        <template #container="{ message, closeCallback }">
            <div class="sk-toast" :class="[message.severity ? `sk-toast-${message.severity}` : '', message.styleClass]">
                <span class="sk-toast-icon"><i :class="iconFor(message)" /></span>

                <div class="sk-toast-body">
                    <p v-if="message.summary" class="sk-toast-summary">{{ message.summary }}</p>
                    <p v-if="message.detail" class="sk-toast-detail">{{ message.detail }}</p>

                    <div v-if="actionsOf(message).length" class="sk-toast-actions">
                        <button
                            v-for="(action, index) in actionsOf(message)"
                            :key="index"
                            type="button"
                            class="sk-toast-btn"
                            :class="action.primary ? 'sk-toast-btn-solid' : 'sk-toast-btn-ghost'"
                            @click="runAction(action, closeCallback)"
                        >
                            {{ action.label }}
                        </button>
                    </div>
                </div>

                <button type="button" class="sk-toast-close" :aria-label="$t('sk-button.close')" @click="closeCallback">
                    <i class="pi pi-times" />
                </button>

                <span v-if="!actionsOf(message).length && message.life" class="sk-toast-progress">
                    <span :style="{ animationDuration: `${message.life}ms` }" />
                </span>
            </div>
        </template>
    </Toast>
</template>

<style scoped></style>
