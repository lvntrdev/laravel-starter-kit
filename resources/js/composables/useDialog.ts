// resources/js/composables/useDialog.ts

import { markRaw, type Component } from 'vue';
import { useRefreshBus } from './useRefreshBus';
import { useApi } from './useApi';

/**
 * Global dialog manager — one dialog instance rendered in AdminLayout.
 *
 * Basic usage:
 *   const dialog = useDialog();
 *   dialog.open(UserForm, { user }, 'Edit User');
 *   dialog.close();
 *
 * Rich header (Material Flat — icon lozenge + subtitle):
 *   dialog.open(UserForm, { user }, 'Kullanıcı Düzenle', {
 *       subtitle: 'Profil bilgilerini güncelle',
 *       icon: 'pi pi-user-edit',
 *       refreshKey: 'users-table',
 *   });
 *
 * Shell footer (slate-100 sticky action bar at the bottom of the modal):
 *   dialog.open(InfoPanel, {}, 'Yayınla', {
 *       icon: 'pi pi-send',
 *       footer: {
 *           text: 'Bu işlem geri alınamaz',
 *           confirmLabel: 'Yayınla',
 *           confirmIcon: 'pi pi-check',
 *           severity: 'primary',
 *           onConfirm: () => publish(),
 *       },
 *   });
 *
 * Footer slots (two component regions — far-left and just-before the buttons):
 *   dialog.open(Form, {}, 'Başlık', {
 *       footer: {
 *           startSlot: MetaInfo,          // far-left region
 *           endSlot: ExtraAction,         // just left of Cancel/Confirm
 *           endSlotProps: { id },
 *           confirmLabel: 'Kaydet',
 *           onConfirm: () => save(),
 *       },
 *   });
 *
 * Edit with async data fetch:
 *   dialog.openAsync(UserForm, '/admin/users/1/data', 'Edit User', {
 *       refreshKey: 'users-table',
 *       mapResponse: (data) => ({ user: data }),
 *   });
 */

export type DialogFooterSeverity = 'secondary' | 'success' | 'info' | 'warn' | 'help' | 'danger' | 'contrast';

export interface DialogFooter {
    /** Hint icon class (PrimeIcons) on the left side of the footer. */
    icon?: string;
    /** Hint text on the left side, next to the icon. */
    text?: string;
    /** Cancel button label. When omitted, falls back to translation `sk-button.cancel`. */
    cancelLabel?: string;
    /** Confirm button label. When omitted, falls back to translation `sk-button.confirm`. */
    confirmLabel?: string;
    /** Confirm button icon class. Default `pi pi-check`. */
    confirmIcon?: string;
    /** Confirm button severity. Default `primary`. */
    severity?: DialogFooterSeverity;
    /** Handler fired when the confirm button is pressed. May be async. */
    onConfirm?: () => void | Promise<void>;
    /** Hide the cancel button entirely. */
    hideCancel?: boolean;
    /** Disable the confirm button. */
    disabled?: boolean;
    /** Show a spinner on the confirm button and block clicks. */
    loading?: boolean;
    /** Component rendered at the far-left of the footer (before the hint/info region). */
    startSlot?: Component;
    /** Props forwarded to {@link startSlot}. */
    startSlotProps?: Record<string, unknown>;
    /** Component rendered just to the left of the Cancel/Confirm buttons. */
    endSlot?: Component;
    /** Props forwarded to {@link endSlot}. */
    endSlotProps?: Record<string, unknown>;
}

interface DialogState {
    visible: boolean;
    component: Component | null;
    props: Record<string, unknown>;
    header: string;
    subtitle: string;
    icon: string;
    width: string;
    loading: boolean;
    footer: DialogFooter | null;
}

interface OpenOptions {
    /** Dialog width override. Default `640px`. */
    width?: string;
    /** Short descriptor rendered under the header title. */
    subtitle?: string;
    /** Icon class (PrimeIcons) shown inside the gradient lozenge at the start of the header. */
    icon?: string;
    /**
     * Opt-in slate-100 footer with action buttons. When provided, AppDialog renders
     * the footer below the body. Components inside should NOT render their own footer
     * in that case to avoid duplication.
     */
    footer?: DialogFooter;
    /** Refresh bus key — auto-injects onSuccess (close + refresh) and onCancel (close) into props. */
    refreshKey?: string;
}

interface OpenAsyncOptions<T = unknown> extends OpenOptions {
    /** Transform API response data into component props */
    mapResponse?: (data: T) => Record<string, unknown>;
}

const state = reactive<DialogState>({
    visible: false,
    component: null,
    props: {},
    header: '',
    subtitle: '',
    icon: '',
    width: '640px',
    loading: false,
    footer: null,
});

/**
 * Pending teardown timer shared across all useDialog() calls. A rapid
 * open → close → open sequence could otherwise let an earlier close()'s
 * 300 ms timer wipe the state of a dialog that has just been re-opened.
 */
let closeTimer: ReturnType<typeof setTimeout> | null = null;

/**
 * markRaw the footer's slot components so reactive() doesn't wrap them
 * (Vue warns when a component definition is made reactive). Returns a fresh
 * object — never mutates the caller's footer.
 */
function normalizeFooter(footer: DialogFooter | null): DialogFooter | null {
    if (!footer) return null;

    const next: DialogFooter = { ...footer };
    if (next.startSlot) next.startSlot = markRaw(next.startSlot);
    if (next.endSlot) next.endSlot = markRaw(next.endSlot);

    return next;
}

export function useDialog() {
    const bus = useRefreshBus();
    const api = useApi();

    /**
     * Build onSuccess / onCancel callbacks when a refreshKey is provided.
     */
    function buildCallbacks(refreshKey?: string): Record<string, unknown> {
        if (!refreshKey) return {};

        return {
            onSuccess: () => {
                close();
                bus.refresh(refreshKey);
            },
            onCancel: () => close(),
        };
    }

    /**
     * Open the dialog with a dynamic component.
     *
     * @param component  Vue component to render inside the dialog.
     * @param props      Props forwarded to the component.
     * @param header     Dialog title string.
     * @param options    Optional overrides (width, subtitle, icon, footer, refreshKey).
     */
    function open(
        component: Component,
        props: Record<string, unknown> = {},
        header: string = '',
        options: OpenOptions = {},
    ): void {
        // Cancel any pending teardown from a previous close() — otherwise
        // that timer would fire mid-use and blank the newly-opened dialog.
        if (closeTimer !== null) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }

        state.component = markRaw(component);
        state.props = { ...buildCallbacks(options.refreshKey), ...props };
        state.header = header;
        state.subtitle = options.subtitle ?? '';
        state.icon = options.icon ?? '';
        state.width = options.width ?? '640px';
        state.footer = normalizeFooter(options.footer ?? null);
        state.loading = false;
        state.visible = true;
    }

    /**
     * Open dialog with a loading state, fetch data from URL, then update props.
     *
     * @param component   Vue component to render.
     * @param url         API endpoint to fetch data from.
     * @param header      Dialog title string.
     * @param options     refreshKey, width, subtitle, icon, footer, mapResponse.
     * @param baseProps   Props to pass immediately (before data arrives).
     */
    async function openAsync<T = unknown>(
        component: Component,
        url: string,
        header: string = '',
        options: OpenAsyncOptions<T> = {},
        baseProps: Record<string, unknown> = {},
    ): Promise<void> {
        const callbacks = buildCallbacks(options.refreshKey);

        open(component, { ...callbacks, ...baseProps }, header, options);
        setLoading(true);

        try {
            const data = await api.get<T>(url);
            const mapped = options.mapResponse ? options.mapResponse(data) : { data };
            state.props = { ...callbacks, ...baseProps, ...mapped };
        } catch {
            close();
        } finally {
            setLoading(false);
        }
    }

    /**
     * Close the dialog.
     * Clears component/props after the PrimeVue hide animation (~300 ms).
     */
    function close(): void {
        state.visible = false;

        if (closeTimer !== null) {
            clearTimeout(closeTimer);
        }

        closeTimer = setTimeout(() => {
            state.component = null;
            state.props = {};
            state.header = '';
            state.subtitle = '';
            state.icon = '';
            state.footer = null;
            state.loading = false;
            closeTimer = null;
        }, 300);
    }

    function setLoading(val: boolean): void {
        state.loading = val;
    }

    /**
     * Dynamically update the shell footer (useful for toggling loading/disabled
     * from inside the rendered component without re-opening the dialog).
     */
    function setFooter(footer: DialogFooter | null): void {
        state.footer = normalizeFooter(footer);
    }

    /** Merge partial changes into the existing footer. No-op when no footer set. */
    function patchFooter(partial: Partial<DialogFooter>): void {
        if (!state.footer) return;
        state.footer = normalizeFooter({ ...state.footer, ...partial });
    }

    return { open, openAsync, close, setLoading, setFooter, patchFooter, state };
}
