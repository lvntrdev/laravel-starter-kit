// resources/js/composables/useConfirm.ts

import { useConfirm as usePrimeConfirm } from 'primevue/useconfirm';

/**
 * Composable for confirmation dialogs using PrimeVue ConfirmDialog.
 * Wraps PrimeVue's useConfirm with English defaults and simpler API.
 *
 * Requirements:
 *   - <ConfirmDialog /> must be placed in the layout.
 *   - ConfirmationService must be registered in app.ts.
 *
 * Usage:
 *   const { confirmDelete, confirmAction } = useConfirm();
 *   confirmDelete(() => router.delete(`/admin/users/${id}`));
 */
export function useConfirm() {
    const confirm = usePrimeConfirm();

    /**
     * Show a delete confirmation dialog.
     */
    function confirmDelete(onAccept: () => void, message?: string) {
        confirm.require({
            message: message ?? 'Are you sure you want to delete this record? This action cannot be undone.',
            header: 'Delete Confirmation',
            icon: 'pi pi-exclamation-triangle',
            rejectLabel: 'Cancel',
            acceptLabel: 'Delete',
            rejectClass: 'p-button-secondary p-button-outlined',
            acceptClass: 'p-button-danger',
            accept: onAccept,
        });
    }

    /**
     * Show a generic confirmation dialog.
     */
    function confirmAction(options: { message: string; header?: string; onAccept: () => void; severity?: string }) {
        confirm.require({
            message: options.message,
            header: options.header ?? 'Confirmation',
            icon: 'pi pi-question-circle',
            rejectLabel: 'Cancel',
            acceptLabel: 'Confirm',
            rejectClass: 'p-button-secondary p-button-outlined',
            accept: options.onAccept,
        });
    }

    return {
        confirmDelete,
        confirmAction,
    };
}
