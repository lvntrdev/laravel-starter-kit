<script setup lang="ts">
    import { Head } from '@inertiajs/vue3';
    import AdminLayout from '@/layouts/AdminLayout.vue';
    import SkCard from '@lvntr/components/ui/SkCard.vue';

    // Built-in PrimeVue severities — kept as PrimeVue's own styling (the theme
    // only ADDS the Tailwind families, it does not repaint these).
    const severities = [
        { sev: undefined, label: 'Primary' },
        { sev: 'secondary', label: 'Secondary' },
        { sev: 'success', label: 'Success' },
        { sev: 'info', label: 'Info' },
        { sev: 'warn', label: 'Warn' },
        { sev: 'help', label: 'Help' },
        { sev: 'danger', label: 'Danger' },
        { sev: 'contrast', label: 'Contrast' },
    ] as const;

    // Every Tailwind family (+ the custom mauve / olive / mist / taupe) usable
    // directly as a `severity` thanks to the `[data-p-severity]` theme rules.
    const colors = [
        'slate', 'gray', 'zinc', 'neutral', 'stone',
        'mauve', 'olive', 'mist', 'taupe',
        'red', 'orange', 'amber', 'yellow', 'lime', 'green', 'emerald', 'teal',
        'cyan', 'sky', 'blue', 'indigo', 'violet', 'purple', 'fuchsia', 'pink', 'rose',
    ] as const;

    const cap = (s: string): string => s.charAt(0).toUpperCase() + s.slice(1);
</script>

<template>
    <Head title="Butonlar" />

    <AdminLayout title="Butonlar" subtitle="Button bileşeni — severity, varyantlar ve Tailwind renkleri.">
        <template #page-actions>
            <a href="https://primevue.org/button/" target="_blank" rel="noopener noreferrer">
                <Button label="Dokümantasyon" icon="pi pi-book" outlined size="small" />
            </a>
        </template>

        <Message severity="info" :closable="false" class="mb-6">
            <span class="text-[13.5px] leading-relaxed">
                <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code>
                türü butonun rengini belirler. Yerleşik önem dereceleri ile birlikte her Tailwind renk ailesi
                (v4.2 ile gelen <strong>mauve · olive · mist · taupe</strong> dahil) doğrudan bir
                <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">severity</code>
                olarak kullanılabilir — ör. <code class="rounded bg-surface-100 px-1 py-px font-mono text-[11.5px] text-surface-600 dark:bg-surface-800 dark:text-surface-300">&lt;Button severity="indigo" label="Kaydet" /&gt;</code>.
                Dolu, çerçeveli, metin, yükseltilmiş, yuvarlatılmış ve yalnızca-ikon varyantlarının tamamıyla çalışır.
            </span>
        </Message>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <!-- 01 · PrimeVue native severities -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">01</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">PrimeVue · Önem Dereceleri</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">Yerleşik severity'ler PrimeVue stiliyle korunur — dolu, çerçeveli ve metin.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button v-for="s in severities" :key="'f-' + s.label" :severity="s.sev" :label="s.label" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button v-for="s in severities" :key="'o-' + s.label" :severity="s.sev" :label="s.label" outlined />
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button v-for="s in severities" :key="'t-' + s.label" :severity="s.sev" :label="s.label" text />
                    </div>
                </div>
            </SkCard>

            <!-- 02 · Variants -->
            <SkCard>
                <div class="mb-4 flex items-start gap-3">
                    <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-md font-mono text-[11.5px] font-semibold"
                          :style="{ color: 'var(--p-primary-color)', background: 'color-mix(in srgb, var(--p-primary-color) 10%, transparent)' }">02</span>
                    <div>
                        <h3 class="text-[14.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">Varyantlar</h3>
                        <p class="mt-0.5 text-[12.5px] leading-relaxed text-surface-500 dark:text-surface-400">İkon, yuvarlatılmış, yalnızca-ikon, boyutlar ve durumlar.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-3.5">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button label="Kaydet" icon="pi pi-check" />
                        <Button label="İleri" icon="pi pi-arrow-right" icon-pos="right" />
                        <Button label="Sil" icon="pi pi-trash" severity="danger" outlined />
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button label="Pill" rounded />
                        <Button label="Pill" severity="secondary" rounded outlined />
                        <Button icon="pi pi-heart" severity="danger" rounded aria-label="Beğen" />
                        <Button icon="pi pi-check" aria-label="Onayla" />
                        <Button icon="pi pi-cog" severity="secondary" outlined aria-label="Ayarlar" />
                        <Button icon="pi pi-bookmark" text aria-label="Yer imi" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button label="Küçük" icon="pi pi-check" size="small" />
                        <Button label="Normal" icon="pi pi-check" />
                        <Button label="Büyük" icon="pi pi-check" size="large" />
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5">
                        <Button label="Yükleniyor" loading />
                        <Button label="Pasif" disabled />
                    </div>
                </div>
            </SkCard>

            <!-- 03 · Per-color full variant set -->
            <SkCard v-for="c in colors" :key="c">
                <div class="mb-4 flex items-center gap-2.5 border-b border-surface-200 pb-3 dark:border-surface-700">
                    <!-- Decorative swatch — a tiny filled Button reuses the exact themed
                         color (var(--btn-c)) so it never drifts from the palette. -->
                    <Button :severity="c" rounded aria-hidden="true" tabindex="-1"
                            class="pointer-events-none h-3.5 w-3.5 min-w-0 shrink-0 p-0" />
                    <span class="font-mono text-[12.5px] font-semibold tracking-tight text-surface-800 dark:text-surface-100">{{ cap(c) }}</span>
                    <span class="ml-auto font-mono text-[10.5px] text-surface-400 dark:text-surface-500">severity="{{ c }}"</span>
                </div>

                <div class="flex flex-col gap-3.5">
                    <!-- Filled -->
                    <div class="flex flex-col gap-1.5">
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Filled</span>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Button :severity="c" :label="cap(c)" />
                            <Button :severity="c" :label="cap(c)" icon="pi pi-check" />
                            <Button :severity="c" :label="cap(c)" disabled />
                        </div>
                    </div>
                    <!-- Raised / Rounded -->
                    <div class="flex flex-col gap-1.5">
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Raised · Rounded</span>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Button :severity="c" :label="cap(c)" raised />
                            <Button :severity="c" label="Pill" rounded />
                            <Button :severity="c" label="Pill" rounded outlined />
                        </div>
                    </div>
                    <!-- Outlined / Text -->
                    <div class="flex flex-col gap-1.5">
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Outlined · Text</span>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Button :severity="c" label="Outline" outlined />
                            <Button :severity="c" label="Outline" icon="pi pi-arrow-right" icon-pos="right" outlined />
                            <Button :severity="c" label="Text" text />
                        </div>
                    </div>
                    <!-- Icon only -->
                    <div class="flex flex-col gap-1.5">
                        <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-surface-400">Icon Only</span>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <Button :severity="c" icon="pi pi-check" :aria-label="c" />
                            <Button :severity="c" icon="pi pi-star" rounded :aria-label="c" />
                            <Button :severity="c" icon="pi pi-heart" rounded outlined :aria-label="c" />
                            <Button :severity="c" icon="pi pi-cog" text :aria-label="c" />
                        </div>
                    </div>
                </div>
            </SkCard>
        </div>
    </AdminLayout>
</template>
