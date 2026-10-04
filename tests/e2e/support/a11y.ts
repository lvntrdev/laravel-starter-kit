import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';

/**
 * Fail on WCAG 2.x A/AA violations axe rates `serious` or `critical`.
 * Minor/moderate findings are left out so the smoke suite stays a regression
 * gate, not an audit — widen the impact list once the baseline is clean.
 */
export async function expectNoA11yViolations(page: Page, scope?: string): Promise<void> {
    // Let enter transitions (dialog/popover fade-in) settle first: mid-fade text is
    // half-transparent and color-contrast would measure that instead of the real
    // colors. Infinite animations (spinners) never finish, so they are skipped.
    await page.evaluate(() =>
        Promise.all(
            document
                .getAnimations()
                .filter((a) => a.effect?.getTiming().iterations !== Infinity)
                .map((a) => a.finished.catch(() => undefined)),
        ),
    );

    let builder = new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']);

    if (scope) {
        builder = builder.include(scope);
    }

    const { violations } = await builder.analyze();
    const blocking = violations
        .filter((v) => v.impact === 'serious' || v.impact === 'critical')
        .map((v) => `${v.id} (${v.impact}): ${v.help} → ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);

    expect(blocking, blocking.join('\n')).toEqual([]);
}
