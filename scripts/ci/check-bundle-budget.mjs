#!/usr/bin/env node
/**
 * CI guard: keeps the Inertia initial payload from silently growing.
 *
 * Inertia pages are resolved lazily (`stubs/resources/js/app.ts`), so every
 * page lands in its own dynamically imported chunk. What the browser must
 * download before the first paint is therefore the entry chunk plus the chunks
 * it pulls in STATICALLY (`imports`) — `dynamicImports` are deliberately
 * excluded, they are the per-page/per-feature chunks that load on demand.
 *
 * Run after `vite build` (client, not `--ssr`); reads the client manifest,
 * gzips every file in that static closure and fails when the sum exceeds
 * BUDGET. Raising BUDGET is a conscious decision, not a side effect.
 */
import { readFileSync, existsSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

/**
 * Gzipped byte ceiling for the initial payload (entry + static imports).
 * Measured at 399_759 B (390.4 kB) right after lazy page resolution landed;
 * ~25% headroom on top so ordinary feature work does not trip the gate.
 */
const BUDGET = 500_000;

const __dirname = dirname(fileURLToPath(import.meta.url));
const buildDir = resolve(__dirname, '../../stubs/public/build');
const manifestPath = join(buildDir, 'manifest.json');

if (!existsSync(manifestPath)) {
    console.error(`Bundle budget: manifest not found at ${manifestPath}. Run \`npm run build\` in stubs/ first.`);
    process.exit(1);
}

/** @type {Record<string, { file: string, isEntry?: boolean, imports?: string[], css?: string[] }>} */
const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));

const entryKey =
    Object.keys(manifest).find((key) => manifest[key].isEntry && key.endsWith('.ts')) ??
    Object.keys(manifest).find((key) => manifest[key].isEntry && manifest[key].file.endsWith('.js'));

if (!entryKey) {
    console.error('Bundle budget: no JS entry chunk found in the manifest.');
    process.exit(1);
}

// Static closure of the entry: the chunk itself plus everything reachable
// through `imports`. CSS attached to a visited chunk ships with it, so it
// counts too (the CSS-only entry is already listed there by Vite).
const visited = new Set();
const files = new Set();
const queue = [entryKey];

while (queue.length > 0) {
    const key = queue.shift();
    if (visited.has(key)) {
        continue;
    }
    visited.add(key);

    const chunk = manifest[key];
    if (!chunk) {
        continue;
    }

    if (chunk.file) {
        files.add(chunk.file);
    }
    for (const css of chunk.css ?? []) {
        files.add(css);
    }
    for (const imported of chunk.imports ?? []) {
        queue.push(imported);
    }
}

const rows = [...files]
    .map((file) => {
        const path = join(buildDir, file);
        if (!existsSync(path)) {
            console.error(`Bundle budget: manifest references a missing file: ${file}`);
            process.exit(1);
        }
        const raw = readFileSync(path);

        return { file, raw: raw.length, gzip: gzipSync(raw).length };
    })
    .sort((a, b) => b.gzip - a.gzip);

const total = rows.reduce((sum, row) => sum + row.gzip, 0);
const kb = (bytes) => `${(bytes / 1024).toFixed(1)} kB`;

console.log(`Initial payload — entry \`${entryKey}\` + ${visited.size - 1} static import(s), ${rows.length} file(s):`);
for (const row of rows) {
    console.log(`  ${kb(row.gzip).padStart(10)} gzip  (${kb(row.raw).padStart(10)} raw)  ${row.file}`);
}
console.log(`  ${'-'.repeat(40)}`);
console.log(`  ${kb(total).padStart(10)} gzip total — budget ${kb(BUDGET)} (${((total / BUDGET) * 100).toFixed(1)}% used)`);

if (total > BUDGET) {
    console.error(
        `\nBundle budget exceeded by ${kb(total - BUDGET)}. ` +
            'Move the growth behind a dynamic import, or raise BUDGET in scripts/ci/check-bundle-budget.mjs on purpose.',
    );
    process.exit(1);
}
