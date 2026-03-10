import type { Page } from 'playwright';

export async function loginToLegacySystem(
    page: Page,
    baseUrl: string,
    username: string,
    password: string,
): Promise<void> {
    await page.goto(baseUrl);
    await page.waitForLoadState('networkidle');

    await page.getByRole('textbox', { name: 'E-mail' }).fill(username);
    await page.getByRole('textbox', { name: 'Heslo' }).fill(password);
    await page.getByRole('button', { name: 'Přihlásit se' }).click();

    // Wait for homepage to load after login (page may redirect with loading animation)
    try {
        await page.locator('body[current_section="homepage"]').waitFor({ timeout: 30_000 });
    } catch {
        throw new Error('Login failed - homepage not reached');
    }
}
