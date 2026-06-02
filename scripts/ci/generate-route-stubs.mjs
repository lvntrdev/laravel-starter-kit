#!/usr/bin/env node
/**
 * CI fallback: Wayfinder normally generates `stubs/resources/js/routes/*.ts`
 * via `php artisan wayfinder:generate`, but the node CI job has no PHP.
 *
 * This script writes minimal route stubs so `vite build` and `vue-tsc` can
 * resolve `@/routes/*` imports. The output directory is gitignored — these
 * files never ship in the package and the host app regenerates them on its
 * first `npm run dev`.
 */
import { mkdirSync, writeFileSync, rmSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const stubsDir = resolve(__dirname, '../../stubs');
const routesDir = join(stubsDir, 'resources/js/routes');

if (existsSync(routesDir)) {
    rmSync(routesDir, { recursive: true, force: true });
}
mkdirSync(routesDir, { recursive: true });

const helper = `// Auto-generated CI stub. Wayfinder regenerates the route files in real host apps.
// The signature is intentionally permissive — actual wayfinder routes accept
// model instances, parameter objects, primitives, etc.
export const r = (url: string) => ({
    url: (..._args: unknown[]): string => url,
    action: url,
    method: 'get' as const,
});
`;
writeFileSync(join(routesDir, '_stub-helper.ts'), helper);

/**
 * Each entry maps a route module name to either a flat array of action names
 * or a nested descriptor for namespaced actions (e.g. settings.update.general).
 */
const ROUTES = {
    'activity-logs': ['index', 'show', 'dtApi'],
    'api-clients': ['dtApi', 'destroy', 'data', 'store', 'update'],
    'api-routes': ['index', 'regenerateDocs', 'syncPostman', 'syncApidog'],
    'api-tokens': ['dtApi', 'destroy', 'store'],
    'browser-sessions': ['index', 'destroy'],
    dashboard: ['index'],
    files: ['index'],
    locale: ['update'],
    logs: ['index', 'dtApi', 'destroy', 'show', 'entries'],
    roles: [
        'index',
        'create',
        'store',
        'edit',
        'update',
        'destroy',
        'bulk',
        'dtApi',
        'syncPermissions',
    ],
    settings: {
        flat: ['index', 'testMail'],
        nested: {
            upload: ['logo'],
            delete: ['logo'],
            update: [
                'general',
                'mail',
                'auth',
                'turnstile',
                'storage',
                'postman',
                'apidog',
                'fileManager',
                'security',
            ],
        },
    },
    'system-health': ['index', 'run'],
    users: [
        'index',
        'dtApi',
        'destroy',
        'bulk',
        'data',
        'store',
        'update',
        'uploadAvatar',
        'deleteAvatar',
    ],
    'user-password': ['update'],
    'user-profile-information': ['update'],
};

function buildFlatModule(routeName, actions) {
    const lines = actions.map((a) => `export const ${a} = r('/${routeName}/${a}');`);
    lines.push(`export default { ${actions.join(', ')} };`);
    return lines.join('\n') + '\n';
}

function buildSettingsModule(routeName, def) {
    const flatLines = def.flat.map((a) => `export const ${a} = r('/${routeName}/${a}');`);
    const nestedNames = Object.keys(def.nested);
    const nestedLines = [];

    for (const ns of nestedNames) {
        const inner = def.nested[ns]
            .map((sub) => `    ${sub}: r('/${routeName}/${ns}-${sub}')`)
            .join(',\n');
        // `delete` is a reserved word — emit under an alias and only re-export
        // it as a default-object property.
        const localName = ns === 'delete' ? '_delete' : ns;
        nestedLines.push(`const ${localName} = {\n${inner},\n};`);
        if (ns !== 'delete') {
            nestedLines.push(`export { ${localName} as ${ns} };`);
        }
    }

    const exportEntries = [
        ...def.flat,
        ...nestedNames.map((ns) => (ns === 'delete' ? `delete: _delete` : ns)),
    ];

    return [
        ...flatLines,
        ...nestedLines,
        `export default { ${exportEntries.join(', ')} };`,
    ].join('\n') + '\n';
}

for (const [name, def] of Object.entries(ROUTES)) {
    const filePath = join(routesDir, `${name}.ts`);
    const header = `import { r } from './_stub-helper';\n`;
    const body = Array.isArray(def) ? buildFlatModule(name, def) : buildSettingsModule(name, def);
    writeFileSync(filePath, header + body);
}

console.log(`Generated ${Object.keys(ROUTES).length} route stubs in ${routesDir}`);
