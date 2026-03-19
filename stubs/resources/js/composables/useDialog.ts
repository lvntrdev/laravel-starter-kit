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
 * With auto close + refresh:
 *   const dialog = useDialog();
 *   dialog.open(UserForm, { user }, 'Edit User', { refreshKey: 'users-table' });
 *   // onSuccess and onCancel are automatically injected into props
 *
 * Edit with async data fetch:
 *   dialog.openAsync(UserForm, '/admin/users/1/data', 'Edit User', {
 *       refreshKey: 'users-table',
 *       mapResponse: (data) => ({ user: data }),
 *   });
 */

interface DialogState {
    visible: boolean;
    component: Component | null;
    props: Record<string, unknown>;
    header: string;
    width: string;
    loading: boolean;
}

interface OpenOptions {
    /** Dialog width override */
    width?: string;
    /** Refresh bus key — auto-injects onSuccess (close + refresh) and onCancel (close) */
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
    width: '640px',
    loading: false,
});

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
     * @param options    Optional overrides (width, refreshKey).
     */
    function open(
        component: Component,
        props: Record<string, unknown> = {},
        header: string = '',
        options: OpenOptions = {},
    ): void {
        state.component = markRaw(component);
        state.props = { ...buildCallbacks(options.refreshKey), ...props };
        state.header = header;
        state.width = options.width ?? '640px';
        state.loading = false;
        state.visible = true;
    }

    /**
     * Open dialog with a loading state, fetch data from URL, then update props.
     *
     * @param component   Vue component to render.
     * @param url         API endpoint to fetch data from.
     * @param header      Dialog title string.
     * @param options     refreshKey, width, mapResponse.
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
        setTimeout(() => {
            state.component = null;
            state.props = {};
            state.header = '';
            state.loading = false;
        }, 300);
    }

    function setLoading(val: boolean): void {
        state.loading = val;
    }

    return { open, openAsync, close, setLoading, state };
}
