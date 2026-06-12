// resources/js/composables/usePageHeader.ts

import type { InjectionKey } from 'vue';

/**
 * Sayfa başlığı bağlamı — AdminLayout sağlar, geri-butonlu form sayfalarının
 * ilk kartı tüketir. Aura temasında, `header-in-card` ile opt-in eden geri-butonlu
 * sayfalarda başlık + alt başlık + geri butonu ayrı bir page-header yerine ilk
 * kartın başlığında gösterilir.
 *
 * `active` false iken (aura değil, geri butonu yok, opt-in yok veya dialog modu —
 * inject default'u) tüketici hiçbir şey yapmaz; kart kendi başlığını korur.
 */
export interface PageHeaderContext {
    /** Aura + geri butonu + sayfa `header-in-card` seçti → ilk karta gömülür. */
    active: boolean;
    title: string;
    subtitle: string;
    goBack: () => void;
}

export const PAGE_HEADER_KEY: InjectionKey<PageHeaderContext> = Symbol('sk-page-header');

const INACTIVE: PageHeaderContext = {
    active: false,
    title: '',
    subtitle: '',
    goBack: () => {},
};

/** Geri-butonlu sayfaların ilk kartı bunu okur. Provider yoksa pasif bağlam döner. */
export function usePageHeader(): PageHeaderContext {
    return inject(PAGE_HEADER_KEY, INACTIVE);
}
