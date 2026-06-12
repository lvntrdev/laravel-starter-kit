/**
 * Mime ve klasör renk paletleri — FileManager + FileGrid ortak kullanır.
 * Tailwind sınıfları statik string olarak tutulur (JIT purge güvenliği).
 */

export interface MimePalette {
    /** pi ikon sınıfı (ör. 'pi pi-image') */
    icon: string;
    /** Dolgu rengi — doc tile / renk kenarı (ör. 'bg-emerald-500') */
    solid: string;
    /** Yumuşak zemin — ikon kareleri (ör. 'bg-emerald-500/15') */
    tint: string;
    /** Metin / ikon rengi (ör. 'text-emerald-500') */
    text: string;
    /** Kart önizleme alanı zemin tonu */
    previewTint: string;
}

const MIME_PALETTES = {
    image: {
        icon: 'pi pi-image',
        solid: 'bg-emerald-500',
        tint: 'bg-emerald-500/15',
        text: 'text-emerald-500',
        previewTint: 'bg-emerald-500/[0.08] dark:bg-emerald-500/10',
    },
    video: {
        icon: 'pi pi-video',
        solid: 'bg-indigo-500',
        tint: 'bg-indigo-500/15',
        text: 'text-indigo-500',
        previewTint: 'bg-indigo-500/[0.08] dark:bg-indigo-500/10',
    },
    pdf: {
        icon: 'pi pi-file-pdf',
        solid: 'bg-red-500',
        tint: 'bg-red-500/15',
        text: 'text-red-500',
        previewTint: 'bg-red-500/[0.08] dark:bg-red-500/10',
    },
    audio: {
        icon: 'pi pi-volume-up',
        solid: 'bg-amber-500',
        tint: 'bg-amber-500/15',
        text: 'text-amber-500',
        previewTint: 'bg-amber-500/[0.08] dark:bg-amber-500/10',
    },
    archive: {
        icon: 'pi pi-box',
        solid: 'bg-sky-500',
        tint: 'bg-sky-500/15',
        text: 'text-sky-500',
        previewTint: 'bg-sky-500/[0.08] dark:bg-sky-500/10',
    },
    word: {
        icon: 'pi pi-file-word',
        solid: 'bg-blue-500',
        tint: 'bg-blue-500/15',
        text: 'text-blue-500',
        previewTint: 'bg-blue-500/[0.08] dark:bg-blue-500/10',
    },
    excel: {
        icon: 'pi pi-file-excel',
        solid: 'bg-teal-500',
        tint: 'bg-teal-500/15',
        text: 'text-teal-500',
        previewTint: 'bg-teal-500/[0.08] dark:bg-teal-500/10',
    },
    text: {
        icon: 'pi pi-file-edit',
        solid: 'bg-slate-500',
        tint: 'bg-slate-500/15',
        text: 'text-slate-500',
        previewTint: 'bg-slate-500/[0.08] dark:bg-slate-500/10',
    },
    other: {
        icon: 'pi pi-file',
        solid: 'bg-slate-500',
        tint: 'bg-slate-500/15',
        text: 'text-slate-500',
        previewTint: 'bg-slate-500/[0.08] dark:bg-slate-500/10',
    },
} as const satisfies Record<string, MimePalette>;

export function paletteForMime(mime: string): MimePalette {
    if (mime.startsWith('image/')) return MIME_PALETTES.image;
    if (mime.startsWith('video/')) return MIME_PALETTES.video;
    if (mime === 'application/pdf') return MIME_PALETTES.pdf;
    if (mime.startsWith('audio/')) return MIME_PALETTES.audio;
    if (
        mime.includes('zip') ||
        mime.includes('compressed') ||
        mime.includes('archive') ||
        mime === 'application/x-tar' ||
        mime === 'application/gzip'
    ) {
        return MIME_PALETTES.archive;
    }
    if (mime.includes('word')) return MIME_PALETTES.word;
    if (mime.includes('excel') || mime.includes('spreadsheet')) return MIME_PALETTES.excel;
    if (mime.startsWith('text/')) return MIME_PALETTES.text;
    return MIME_PALETTES.other;
}

export interface FolderPalette {
    solid: string;
    tint: string;
    text: string;
}

const FOLDER_PALETTES: FolderPalette[] = [
    { solid: 'bg-indigo-500', tint: 'bg-indigo-500/15', text: 'text-indigo-500' },
    { solid: 'bg-rose-500', tint: 'bg-rose-500/15', text: 'text-rose-500' },
    { solid: 'bg-emerald-500', tint: 'bg-emerald-500/15', text: 'text-emerald-500' },
    { solid: 'bg-amber-500', tint: 'bg-amber-500/15', text: 'text-amber-500' },
    { solid: 'bg-sky-500', tint: 'bg-sky-500/15', text: 'text-sky-500' },
    { solid: 'bg-purple-500', tint: 'bg-purple-500/15', text: 'text-purple-500' },
];

/**
 * Liste sırasına göre klasör rengi — komşu klasörler her zaman farklı renk alır
 * (id hash'i az klasörde aynı palete düşebiliyordu; tasarım çeşitlilik istiyor).
 */
export function folderPaletteAt(index: number): FolderPalette {
    return FOLDER_PALETTES[Math.abs(index) % FOLDER_PALETTES.length];
}
