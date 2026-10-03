import { createHmac } from 'node:crypto';
import { test, expect, type Page } from '@playwright/test';

/**
 * Two-factor authentication, end to end: turn 2FA on from the profile page
 * with a real TOTP code, prove the next login is challenged and accepts a
 * recovery code, then turn 2FA off again.
 *
 * Runs on its own seeded account (scripts/e2e/fixtures/E2EAdminSeeder.php),
 * not the smoke spec's admin. A run that fails with 2FA still on is reset by
 * re-running that seeder.
 *
 * The second login uses a recovery code rather than a fresh TOTP: Fortify
 * rejects a code it has already accepted, and activation just used the
 * current one.
 */
const EMAIL = 'e2e-2fa@example.test';
const PASSWORD = process.env.E2E_ADMIN_PASSWORD ?? 'e2e-test-password-only';

/** RFC 6238 TOTP with the defaults Fortify's Google2FA engine uses: SHA-1, 6 digits, 30 s step. */
function totp(base32Secret: string): string {
    const bits = [...base32Secret]
        .map((char) => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'.indexOf(char).toString(2).padStart(5, '0'))
        .join('');
    const key = Buffer.from(bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30_000)));
    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0xf;

    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

async function logIn(page: Page) {
    await page.goto('/login');
    await page.getByLabel('Email Address').fill(EMAIL);
    await page.getByLabel('Password', { exact: true }).fill(PASSWORD);
    await page.getByRole('button', { name: 'Sign In' }).click();

    await expect(page).not.toHaveURL(/\/login/);
}

async function openTwoFactorTab(page: Page) {
    await page.goto('/profile');
    await page.getByRole('tab', { name: 'Two-Factor Authentication' }).click();
}

// Every 2FA change on the profile page asks for the password first.
async function confirmPassword(page: Page) {
    const dialog = page.getByRole('dialog', { name: 'Confirm Password' });
    await dialog.getByLabel('Password', { exact: true }).fill(PASSWORD);
    await dialog.getByRole('button', { name: 'Confirm' }).click();

    await expect(dialog).toBeHidden();
}

test('enable 2FA, pass the login challenge with a recovery code, disable 2FA', async ({ page }) => {
    let recoveryCode = '';

    await test.step('enable 2FA with a TOTP code and keep one recovery code', async () => {
        await logIn(page);
        await openTwoFactorTab(page);
        await page.getByRole('button', { name: 'Enable', exact: true }).click();
        await confirmPassword(page);

        // The manual-entry setup key is the only base32 <code> on the page.
        const setupKey = page.locator('code').filter({ hasText: /[A-Z2-7]{16}/ });
        await expect(setupKey).toBeVisible();
        const secret = (await setupKey.textContent())!.replace(/\s/g, '');

        // InputOtp renders one textbox per digit and moves focus as you type.
        const verify = page.getByRole('button', { name: 'Verify & Activate' });
        await page.locator('form').filter({ has: verify }).getByRole('textbox').first().click();
        await page.keyboard.type(totp(secret));
        await verify.click();

        await expect(page.getByText('Two-factor authentication is on')).toBeVisible();

        // Fortify recovery codes are two 10-character halves joined by a dash.
        const code = page.locator('code').filter({ hasText: /[A-Za-z0-9]{10}-[A-Za-z0-9]{10}/ }).first();
        await expect(code).toBeVisible();
        recoveryCode = (await code.textContent())!.trim();
    });

    await test.step('a fresh login is challenged and accepts the recovery code', async () => {
        await page.context().clearCookies();
        await logIn(page);
        await expect(page).toHaveURL(/\/two-factor-challenge/);

        await page.getByRole('link', { name: 'Use a recovery code' }).click();
        await page.getByLabel('Recovery Code').fill(recoveryCode);
        await page.getByRole('button', { name: 'Verify', exact: true }).click();

        await expect(page).not.toHaveURL(/\/(two-factor-challenge|login)/);
    });

    await test.step('disable 2FA again', async () => {
        await openTwoFactorTab(page);
        await page.getByRole('button', { name: 'Disable', exact: true }).click();
        await confirmPassword(page);

        await expect(page.getByRole('button', { name: 'Enable', exact: true })).toBeVisible();
    });
});
