// resources/js/tab-builder/index.ts

export type { TabBuilderConfig, TabItemConfig, TabLayout } from './types';
export { TabsBuilder, TabItemBuilder } from './builder';

import { TabsBuilder, TabItemBuilder } from './builder';

/**
 * Tab builder — fluent API for configuring the <SkTabs> component.
 *
 * @example
 * const config = TB.tabs()
 *   .vertical()
 *   .queryParam('tab')
 *   .addTabs(
 *     TB.item().key('general').label('General').icon('pi pi-user'),
 *     TB.item().key('password').label('Password').icon('pi pi-lock'),
 *     TB.item().key('security').label('Security').icon('pi pi-shield'),
 *   )
 *   .build();
 */
export const TB = {
    tabs: () => new TabsBuilder(),
    item: () => new TabItemBuilder(),
};
