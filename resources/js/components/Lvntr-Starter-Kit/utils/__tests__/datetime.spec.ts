import { beforeEach, describe, expect, it, vi } from 'vitest';

const { getActiveLanguageMock, usePageMock } = vi.hoisted(() => ({
    getActiveLanguageMock: vi.fn(() => 'en-US'),
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: usePageMock,
}));

vi.mock('laravel-vue-i18n', () => ({
    getActiveLanguage: getActiveLanguageMock,
}));

import { formatDate, formatDateTime, formatTime } from '../datetime';

describe('datetime formatting', () => {
    beforeEach(() => {
        getActiveLanguageMock.mockReturnValue('en-US');
        usePageMock.mockReturnValue({
            props: {
                locale: 'en-US',
                timezone: 'UTC',
            },
        });
    });

    it('renders the same instant as different local dates in different timezones', () => {
        const instant = '2024-01-01T01:00:00+00:00';
        usePageMock.mockReturnValue({ props: { locale: 'en-US', timezone: 'America/Los_Angeles' } });

        const losAngeles = formatDate(instant);
        const tokyo = formatDate(instant, { timeZone: 'Asia/Tokyo' });

        expect(losAngeles).not.toBe(tokyo);
        expect(losAngeles).toBe('Dec 31, 2023');
        expect(tokyo).toBe('Jan 1, 2024');
    });

    it('returns an empty string for null values', () => {
        expect(formatDateTime(null)).toBe('');
    });

    it('passes legacy display strings through unchanged', () => {
        expect(formatDateTime('31-12-2024 23:59')).toBe('31-12-2024 23:59');
    });

    it('falls back from an invalid explicit timezone instead of throwing', () => {
        const instant = '2024-01-01T01:00:00+00:00';

        expect(() => formatTime(instant, { timeZone: 'Not/A_Timezone' })).not.toThrow();
        expect(formatTime(instant, { timeZone: 'Not/A_Timezone' })).toBe(formatTime(instant, { timeZone: 'UTC' }));
    });

    it('formats without an Inertia page context', () => {
        usePageMock.mockImplementation(() => {
            throw new Error('No Inertia page context');
        });

        expect(() => formatDateTime('2024-01-01T01:00:00+00:00')).not.toThrow();
        expect(formatDateTime('2024-01-01T01:00:00+00:00')).not.toBe('');
    });
});
