<script setup lang="ts">
    import { Head } from '@inertiajs/vue3';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import SkCard from '@lvntr/components/ui/SkCard.vue';

    // Built-in PrimeVue severities, repainted to the SK palette in the theme CSS.
    const severities = [
        { sev: 'secondary', label: 'Secondary', icon: 'pi pi-bookmark' },
        { sev: 'success', label: 'Success', icon: 'pi pi-check' },
        { sev: 'info', label: 'Info', icon: 'pi pi-info-circle' },
        { sev: 'warn', label: 'Warn', icon: 'pi pi-exclamation-triangle' },
        { sev: 'danger', label: 'Danger', icon: 'pi pi-times-circle' },
        { sev: 'contrast', label: 'Contrast', icon: 'pi pi-bolt' },
    ] as const;

    // Every Tailwind family (+ the custom mauve / olive / mist / taupe) usable
    // directly as a `severity` thanks to the `[data-p]` theme rules.
    const colors = [
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'mauve', 'olive', 'mist', 'taupe',
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
        'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
    ] as const;

    const cap = (s: string): string => s.charAt(0).toUpperCase() + s.slice(1);
</script>

<template>
    <Head title="Etiketler" />

    <AdminLayout title="Etiketler" subtitle="Tag bileşeni — tüm varyantlar ve renkler.">
        <template #page-actions>
            <a href="https://primevue.org/tag/" target="_blank" rel="noopener noreferrer">
                <Button label="Dokümantasyon" icon="pi pi-book" outlined size="small" />
            </a>
        </template>

        <p class="mb-6 max-w-[640px] text-[13.5px] leading-relaxed text-surface-500 dark:text-surface-400">
            <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code>
            türü etiketin rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi
            doğrudan bir <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code>
            olarak kullanılabilir — ör. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Tag severity="indigo" value="Secondary" /&gt;</code>.
        </p>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <!-- 01 · Filled (default) -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">01</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Önem Dereceleri · Dolu</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">Varsayılan dolu etiketler — severity türü etiketin rengini belirler.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Tag v-for="t in severities" :key="t.sev" :severity="t.sev" :value="t.label" />
                </div>
            </SkCard>

            <!-- 02 · Rounded -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">02</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Yuvarlatılmış</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">rounded özelliği hap biçimli, tam yuvarlatılmış kenarlar verir.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Tag v-for="t in severities" :key="t.sev" :severity="t.sev" :value="t.label" rounded />
                </div>
            </SkCard>

            <!-- 03 · With icon -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">03</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">İkonlu</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">icon özelliği ile başına anlamlı bir PrimeIcon eklenir.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Tag v-for="t in severities" :key="t.sev" :severity="t.sev" :value="t.label" :icon="t.icon" />
                </div>
            </SkCard>

            <!-- 04 · Soft / tonal -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">04</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Yumuşak · Tonal</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">Hafif tonlu zemin, koyu renkli etiket — yoğun tablolarda daha sakin durur.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Tag v-for="t in severities" :key="t.sev" :severity="t.sev" :value="t.label" rounded class="p-tag-soft p-tag-dot" />
                </div>
            </SkCard>

            <!-- 05 · Outlined -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">05</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Çerçeveli</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">Şeffaf zemin, renkli halka ve etiket — düşük vurgulu bağlamlar için.</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <Tag v-for="t in severities" :key="t.sev" :severity="t.sev" :value="t.label" class="p-tag-outlined" />
                </div>
            </SkCard>

            <!-- 06 · Removable + sizes -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">06</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Kaldırılabilir & Boyutlar</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">Kapatma düğmeli filtre çipleri; küçük · varsayılan · büyük boyut ölçeği.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Tag severity="info" rounded class="p-tag-soft">
                            <span>Aktif</span>
                            <button type="button" class="p-tag-x" aria-label="Kaldır"><i class="pi pi-times" /></button>
                        </Tag>
                        <Tag severity="success" rounded class="p-tag-soft">
                            <span>Onaylı</span>
                            <button type="button" class="p-tag-x" aria-label="Kaldır"><i class="pi pi-times" /></button>
                        </Tag>
                        <Tag severity="danger" rounded>
                            <span>Acil</span>
                            <button type="button" class="p-tag-x" aria-label="Kaldır"><i class="pi pi-times" /></button>
                        </Tag>
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Tag severity="info" value="Small" class="p-tag-sm" />
                        <Tag severity="info" value="Normal" />
                        <Tag severity="info" value="Large" class="p-tag-lg" />
                    </div>
                </div>
            </SkCard>

            <!-- 07 · All Tailwind colors -->
            <SkCard class="xl:col-span-2">
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">07</span>
                    <div class="flex-1">
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Tüm Tailwind Renkleri</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">{{ colors.length }} renk ailesi (mauve · olive · mist · taupe dahil) — dolu, yumuşak ve çerçeveli.</p>
                    </div>
                    <span class="inline-flex h-8 shrink-0 items-center gap-2 rounded-md border border-surface-200 px-2.5 font-mono text-[11px] text-surface-500 dark:border-surface-700 dark:text-surface-400">
                        <i class="pi pi-palette text-[12px]" />{{ colors.length }} renk
                    </span>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Filled</span>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Tag v-for="c in colors" :key="'f-' + c" :severity="c" :value="cap(c)" />
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-1.5">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Soft</span>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Tag v-for="c in colors" :key="'s-' + c" :severity="c" :value="cap(c)" rounded class="p-tag-soft p-tag-dot" />
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-1.5">
                    <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Outlined</span>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Tag v-for="c in colors" :key="'o-' + c" :severity="c" :value="cap(c)" class="p-tag-outlined" />
                    </div>
                </div>
            </SkCard>
        </div>
    </AdminLayout>
</template>
