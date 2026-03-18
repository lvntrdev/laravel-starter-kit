// resources/js/composables/useRefreshBus.ts

/**
 * Global refresh event bus.
 *
 * Components (e.g. DataTable) register refresh callbacks under a key.
 * Any other component can trigger a refresh for one or more keys.
 * Registered callbacks are automatically cleaned up on component unmount.
 *
 * Usage:
 *   // DataTable (or any component) registers itself:
 *   const bus = useRefreshBus()
 *   bus.on('users-table', () => fetchData())
 *
 *   // Dialog triggers refresh after save:
 *   const bus = useRefreshBus()
 *   bus.refresh('users-table')
 *
 *   // Refresh multiple keys at once:
 *   bus.refresh('users-table', 'stats-widget')
 */

const registry = new Map<string, Set<() => void>>();

export function useRefreshBus() {
    /**
     * Register a refresh callback for a key.
     * Automatically unregistered when the calling component unmounts.
     */
    function on(key: string, callback: () => void): void {
        if (!registry.has(key)) {
            registry.set(key, new Set());
        }

        registry.get(key)!.add(callback);

        onUnmounted(() => {
            registry.get(key)?.delete(callback);
        });
    }

    /**
     * Trigger refresh callbacks for one or more keys.
     */
    function refresh(...keys: string[]): void {
        keys.forEach((key) => {
            registry.get(key)?.forEach((cb) => cb());
        });
    }

    /**
     * Trigger refresh callbacks for all registered keys.
     */
    function refreshAll(): void {
        registry.forEach((callbacks) => callbacks.forEach((cb) => cb()));
    }

    return { on, refresh, refreshAll };
}
