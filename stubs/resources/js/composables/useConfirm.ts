import { trans } from 'laravel-vue-i18n';
import { useConfirm as usePrimeConfirm } from 'primevue/useconfirm';

/**
 * Composable for confirmation dialogs using PrimeVue ConfirmDialog.
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

    function confirmDelete(onAccept: () => void, message?: string, icon?: string) {
        confirm.require({
            group: 'app',
            message: message ?? trans('sk-common.confirm_delete_message'),
            header: trans('sk-common.confirm_delete_header'),
            icon: icon ?? 'pi pi-trash',
            rejectLabel: trans('sk-button.cancel'),
            acceptLabel: trans('sk-button.delete'),
            rejectClass: 'p-button-secondary p-button-outlined',
            acceptClass: 'p-button-danger',
            accept: onAccept,
        });
    }

    function confirmAction(options: {
        message: string;
        header?: string;
        icon?: string;
        onAccept: () => void;
        onReject?: () => void;
        acceptLabel?: string;
        rejectLabel?: string;
        acceptClass?: string;
    }) {
        confirm.require({
            group: 'app',
            message: options.message,
            header: options.header ?? trans('sk-common.confirmation'),
            icon: options.icon ?? 'pi pi-question-circle',
            rejectLabel: options.rejectLabel ?? trans('sk-button.cancel'),
            acceptLabel: options.acceptLabel ?? trans('sk-button.confirm'),
            rejectClass: 'p-button-secondary p-button-outlined',
            acceptClass: options.acceptClass ?? '',
            accept: options.onAccept,
            reject: options.onReject,
        });
    }

    return {
        confirmDelete,
        confirmAction,
    };
}
