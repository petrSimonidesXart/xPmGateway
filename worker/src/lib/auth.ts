import type { Page } from 'playwright';

export async function loginToLegacySystem(
    page: Page,
    baseUrl: string,
    username: string,
    password: string,
): Promise<void> {
    await page.goto(`${baseUrl}/login`);
    await page.waitForLoadState('networkidle');

    // Fill login form - using stable selectors
    await page.fill('input[name="username"], #username', username);
    await page.fill('input[name="password"], #password', password);
    await page.click('button[type="submit"], input[type="submit"]');

    // Wait for navigation after login
    await page.waitForLoadState('networkidle');

    // Verify login was successful
    const url = page.url();
    if (url.includes('/login')) {
        throw new Error('Login failed - still on login page');
    }
}
