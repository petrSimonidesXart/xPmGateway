import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';
import { VideoRecorder } from '../lib/video.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://pm.interni-sit.cz';

export async function handleVerifyCredentials(job: Job, api: AdapterApi): Promise<void> {
    const screenshots = new ScreenshotManager(job.id);
    await screenshots.init();

    const recorder = new VideoRecorder(job.id);
    await recorder.init();

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        ...recorder.contextOptions(),
    });
    const page = await context.newPage();

    page.setDefaultTimeout(job.timeout_seconds * 1000);

    try {
        await loginToLegacySystem(
            page,
            LEGACY_PM_BASE_URL,
            job.service_account.username,
            job.service_account.password,
        );
        await screenshots.capture(page, 'login-ok');

        await api.submitResult(job.id, {
            status: 'success',
            result: { message: 'Credentials are valid' },
            screenshots: screenshots.getScreenshots(),
        });

        console.log(`[Worker] Job ${job.id} completed — credentials verified`);
    } catch (error) {
        try { await screenshots.capture(page, 'error'); } catch { /* ignore */ }
        const message = error instanceof Error ? error.message : String(error);
        await api.submitResult(job.id, {
            status: 'failed',
            error: message,
            screenshots: screenshots.getScreenshots(),
        });
        throw error;
    } finally {
        const video = page.video();
        await context.close();
        await recorder.upload(video, api);
        await browser.close();
        await recorder.cleanup();
    }
}
