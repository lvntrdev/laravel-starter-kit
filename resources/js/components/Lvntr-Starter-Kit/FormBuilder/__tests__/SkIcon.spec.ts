import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SkIcon from '../../ui/SkIcon.vue';

// ── Tests: class path ─────────────────────────────────────────────────────────

describe('SkIcon — class path (CSS icon library)', () => {
    it('PrimeIcons class string → <i> elementi render eder', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'pi pi-search' },
        });

        const el = wrapper.find('i');
        expect(el.exists()).toBe(true);
        expect(el.classes()).toContain('sk-icon');
        expect(el.classes()).toContain('pi');
        expect(el.classes()).toContain('pi-search');
    });

    it('MDI class string → <i> elementi render eder (farklı paket)', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'mdi mdi-account' },
        });

        const el = wrapper.find('i');
        expect(el.exists()).toBe(true);
        expect(el.classes()).toContain('sk-icon');
        expect(el.classes()).toContain('mdi');
        expect(el.classes()).toContain('mdi-account');
    });

    it('class path → <img> veya <span> render etmez', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'pi pi-search' },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('span').exists()).toBe(false);
    });

    it('ariaLabel yokken aria-hidden="true" atanır', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'pi pi-search' },
        });

        const el = wrapper.find('i');
        expect(el.attributes('aria-hidden')).toBe('true');
    });

    it('ariaLabel verilince aria-hidden kaldırılır ve aria-label atanır', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'pi pi-search', ariaLabel: 'Ara' },
        });

        const el = wrapper.find('i');
        expect(el.attributes('aria-label')).toBe('Ara');
        expect(el.attributes('aria-hidden')).toBeUndefined();
    });
});

// ── Tests: svg path ───────────────────────────────────────────────────────────

describe('SkIcon — svg path (ham SVG markup)', () => {
    it('<svg …> ile başlayan string → <span class="sk-icon--svg"> içinde render eder', () => {
        const svgMarkup = '<svg width="16" height="16"><circle cx="8" cy="8" r="8"/></svg>';
        const wrapper = mount(SkIcon, {
            props: { icon: svgMarkup },
        });

        const span = wrapper.find('span.sk-icon--svg');
        expect(span.exists()).toBe(true);
        expect(span.classes()).toContain('sk-icon');
        // v-html ile SVG içeriği span'ın innerHTML'ine yazılır
        expect(span.html()).toContain('<circle');
    });

    it('svg path → <i> veya <img> render etmez', () => {
        const svgMarkup = '<svg width="16" height="16"><path d="M0 0"/></svg>';
        const wrapper = mount(SkIcon, {
            props: { icon: svgMarkup },
        });

        expect(wrapper.find('i').exists()).toBe(false);
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('svg span role="img" taşır', () => {
        const svgMarkup = '<svg width="16" height="16"></svg>';
        const wrapper = mount(SkIcon, {
            props: { icon: svgMarkup },
        });

        const span = wrapper.find('span');
        expect(span.attributes('role')).toBe('img');
    });
});

// ── Tests: img path ───────────────────────────────────────────────────────────

describe('SkIcon — img path (URL ve data URI)', () => {
    it('https:// URL → <img> render eder', () => {
        const src = 'https://example.com/icon.svg';
        const wrapper = mount(SkIcon, {
            props: { icon: src },
        });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe(src);
        expect(img.classes()).toContain('sk-icon');
        expect(img.classes()).toContain('sk-icon--img');
    });

    it('data:image/svg+xml;base64,... → <img> render eder (data URI)', () => {
        const dataUri = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTYiLz4=';
        const wrapper = mount(SkIcon, {
            props: { icon: dataUri },
        });

        const img = wrapper.find('img');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe(dataUri);
    });

    it('img path → <i> veya <span> render etmez', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'https://example.com/icon.svg' },
        });

        expect(wrapper.find('i').exists()).toBe(false);
        expect(wrapper.find('span').exists()).toBe(false);
    });

    it('ariaLabel yokken alt boş string olur', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'https://example.com/icon.svg' },
        });

        expect(wrapper.find('img').attributes('alt')).toBe('');
    });

    it('ariaLabel verilince alt olarak atanır', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'https://example.com/icon.svg', ariaLabel: 'Logo' },
        });

        expect(wrapper.find('img').attributes('alt')).toBe('Logo');
    });
});

// ── Tests: auto-detect edge cases ────────────────────────────────────────────

describe('SkIcon — auto-detect edge cases', () => {
    it('http:// (http, büyük/küçük harf yok) → img path', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'http://cdn.example.com/icon.png' },
        });

        expect(wrapper.find('img').exists()).toBe(true);
    });

    it('<svg küçük harfle başlıyorsa svg path kabul edilir', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: '<svg xmlns="http://www.w3.org/2000/svg"></svg>' },
        });

        expect(wrapper.find('span.sk-icon--svg').exists()).toBe(true);
    });

    it('FontAwesome class string → class path', () => {
        const wrapper = mount(SkIcon, {
            props: { icon: 'fa fa-user' },
        });

        const el = wrapper.find('i');
        expect(el.exists()).toBe(true);
        expect(el.classes()).toContain('fa');
        expect(el.classes()).toContain('fa-user');
    });
});
