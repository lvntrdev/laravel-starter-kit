<script setup lang="ts">
    const severityMap: Record<string, string> = {
        danger: 'danger',
        trash: 'danger',
        'exclamation-triangle': 'warn',
        warning: 'warn',
        question: 'info',
        info: 'info',
        check: 'success',
    };

    function getSeverity(icon?: string): string {
        if (!icon) return 'info';
        for (const [key, severity] of Object.entries(severityMap)) {
            if (icon.includes(key)) return severity;
        }
        return 'info';
    }
</script>

<template>
    <ConfirmDialog group="app">
        <template #container="{ message, acceptCallback, rejectCallback }">
            <div class="confirm-dialog-container" :class="`confirm-dialog--${getSeverity(message.icon)}`">
                <div v-if="message.icon" class="confirm-dialog-icon-wrapper">
                    <i :class="message.icon" />
                </div>

                <h3 class="confirm-dialog-header">
                    {{ message.header }}
                </h3>

                <p class="confirm-dialog-message">
                    {{ message.message }}
                </p>

                <div class="confirm-dialog-actions">
                    <button
                        class="confirm-dialog-btn confirm-dialog-btn--cancel"
                        @click="rejectCallback"
                    >
                        {{ message.rejectLabel || 'Cancel' }}
                    </button>
                    <button
                        class="confirm-dialog-btn confirm-dialog-btn--accept"
                        @click="acceptCallback"
                    >
                        {{ message.acceptLabel || 'Confirm' }}
                    </button>
                </div>
            </div>
        </template>
    </ConfirmDialog>
</template>
