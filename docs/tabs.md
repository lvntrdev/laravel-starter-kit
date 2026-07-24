# Tabs

The starter kit uses `SkTabs` with a fluent `TB` builder to keep multi-section screens clean. Settings, profile, and similar screens often grow into multiple sections — tabs give a single route a structured UI without breaking the page into many routes.

## Imports

```ts
import { TB } from '@lvntr/components/TabBuilder/core';
import SkTabs from '@lvntr/components/TabBuilder/SkTabs.vue';
```

## Basic Example

```vue
<script setup lang="ts">
import { TB } from '@lvntr/components/TabBuilder/core';
import SkTabs from '@lvntr/components/TabBuilder/SkTabs.vue';

const tabConfig = TB.tabs()
    .queryParam('tab')
    .addTabs(
        TB.item().key('general').label('General').icon('pi pi-user'),
        TB.item().key('security').label('Security').icon('pi pi-shield'),
        TB.item().key('sessions').label('Sessions').icon('pi pi-desktop'),
    )
    .build();
</script>

<template>
    <SkTabs :config="tabConfig">
        <template #general>
            <p>General content</p>
        </template>

        <template #security>
            <p>Security content</p>
        </template>

        <template #sessions>
            <p>Session content</p>
        </template>
    </SkTabs>
</template>
```

## Tabs Builder API

- `layout('horizontal' | 'vertical')`
- `vertical()`
- `horizontal()`
- `queryParam(string)`
- `class(string)`
- `cardTitle(string)`
- `cardSubtitle(string)`
- `isCard(boolean)`
- `addTabs(...tabs)`

## Tab Item API

- `key(string)`
- `label(string)`
- `icon(string)`
- `description(string)` — secondary line under the label (vertical layout only)
- `iconColor(color)` — colored icon tile preset (vertical layout only); defaults to `slate`. One of: `blue`, `amber`, `emerald`, `purple`, `teal`, `red`, `rose`, `indigo`, `slate`, `pink`, `orange`, `cyan`, `green`, `yellow`
- `badge(value, severity?)` — trailing badge (text or number). Severity: `success` / `warn` / `info` / `danger` / `secondary` (default)
- `checked(value = true)` — trailing green check mark; takes precedence over `badge`
- `permission(...permissions)` — hide the tab unless the user holds at least one of the given permissions (variadic; OR across multiple values — same as `canAny()`)
- `role(...roles)` — hide the tab unless the user holds at least one of the given roles (variadic; OR across multiple values)
- `visible(boolean | () => boolean)`
- `disabled(boolean | () => boolean)`
- `isCard(boolean)`
- `cardTitle(string)`
- `cardSubtitle(string)`

```ts
TB.item().key('billing').label('Billing').permission('billing.view', 'billing.manage'),
TB.item().key('admin-tools').label('Admin Tools').role('admin', 'superadmin'),
```

## Rich Vertical Tabs

Vertical tabs can present a richer sidebar — colored icon tile, description line, trailing badge or check mark. Wrap the sidebar in a PrimeVue Card via `.isCard(true)` at the tabs level for the polished default look:

```vue
<script setup lang="ts">
const tabConfig = TB.tabs()
    .vertical()
    .isCard(true)
    .addTabs(
        TB.item()
            .key('general')
            .label('General')
            .description('App name, language and logo')
            .icon('pi pi-cog')
            .iconColor('blue'),
        TB.item()
            .key('mail')
            .label('Mail')
            .description('SMTP and sender settings')
            .icon('pi pi-envelope')
            .iconColor('emerald')
            .badge(3, 'warn'),
        TB.item()
            .key('storage')
            .label('Storage')
            .description('S3, Spaces and local disk')
            .icon('pi pi-database')
            .iconColor('purple')
            .checked(),
    )
    .build();
</script>
```

`description`, `iconColor`, `badge`, and `checked` are ignored in horizontal layout.

## Useful Features

- vertical or horizontal layout
- rich vertical sidebar with icon tiles, descriptions, badges, check marks
- query string sync via `useUrlTab()`
- role-based and permission-based visibility
- per-tab disabled logic
- optional card wrappers with title and subtitle at both tab and container level

## Built-in Behavior

`SkTabs` already includes:

- query string synchronization through `useUrlTab()`
- vertical sidebar mode
- optional `sidebar-header` and `sidebar-footer` slots in vertical layout
- slot-based content keyed by the tab id

The active tab is exposed from the component via `defineExpose`, so parent components can access it when needed.

## Good Fit

- settings screens
- profile screens
- long create/edit views split into logical sections
