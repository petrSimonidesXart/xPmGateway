import { chromium } from 'playwright';
import { join } from 'node:path';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';

import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://hirola.xart.cz/pmdev/public/index.php';

/**
 * Navigate to a filter URL, apply the filter, then export results to CSV.
 * The filter_url should be a full path_info URL with filter parameters,
 * e.g. "?path_info=tasks&filter[project]=5&filter[status]=open"
 */
export async function handleExportFilteredTasks(job: Job, api: AdapterApi): Promise<void> {
    const screenshots = new ScreenshotManager(job.id);
    await screenshots.init();

    // Create temp dir for downloaded file
    const downloadDir = await mkdtemp(join(tmpdir(), 'pm-export-'));

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        acceptDownloads: true,
    });
    const page = await context.newPage();

    page.setDefaultTimeout(job.timeout_seconds * 1000);

    try {
        const { filter_url } = job.payload as { filter_url: string };

        // Build full URL — filter_url can be absolute or relative
        const fullUrl = filter_url.startsWith('http')
            ? filter_url
            : `${LEGACY_PM_BASE_URL}${filter_url.startsWith('?') ? '' : '?path_info='}${filter_url}`;

        // Step 1: Login
        await loginToLegacySystem(
            page,
            LEGACY_PM_BASE_URL,
            job.service_account.username,
            job.service_account.password,
        );
        await screenshots.capture(page, 'login-ok');

        // Step 2: Navigate to the filter URL
        await page.goto(fullUrl);
        await page.waitForLoadState('networkidle');
        await screenshots.capture(page, 'filter-page');

        // Step 3: Click the filter button to apply filters
        const filterButton = page.locator(
            'button:has-text("Filtrovat"), '
            + 'button:has-text("Filter"), '
            + 'input[type="submit"][value*="Filtr"], '
            + 'button[type="submit"]:has-text("Filtr"), '
            + '.filter-submit, '
            + '#filter-submit',
        );

        if (await filterButton.count() > 0) {
            await filterButton.first().click();
            await page.waitForLoadState('networkidle');
            await screenshots.capture(page, 'filter-applied');
        } else {
            // Filter may already be applied via URL parameters
            await screenshots.capture(page, 'filter-already-applied');
        }

        // Step 4: Click the CSV export button and wait for download
        const exportButton = page.locator(
            'a:has-text("Export"), '
            + 'a:has-text("CSV"), '
            + 'button:has-text("Export"), '
            + 'button:has-text("CSV"), '
            + 'a[href*="export"], '
            + 'a[href*="csv"], '
            + '.export-btn, '
            + '.csv-export, '
            + '#export-csv',
        );

        if (await exportButton.count() === 0) {
            throw new Error('Export/CSV button not found on the page');
        }

        // Start waiting for download before clicking
        const downloadPromise = page.waitForEvent('download', { timeout: 60_000 });
        await exportButton.first().click();
        const download = await downloadPromise;

        await screenshots.capture(page, 'export-clicked');

        // Step 5: Save downloaded file
        const suggestedFilename = download.suggestedFilename() || 'export.csv';
        const downloadPath = join(downloadDir, suggestedFilename);
        await download.saveAs(downloadPath);

        // Step 6: Upload as artifact
        const artifact = await api.uploadArtifact(job.id, downloadPath, {
            filename: suggestedFilename,
            mimeType: 'text/csv',
            metadata: {
                filter_url: filter_url,
                source: 'export_filtered_tasks',
            },
        });

        await screenshots.capture(page, 'done');

        // Step 7: Submit result
        await api.submitResult(job.id, {
            status: 'success',
            result: {
                filename: suggestedFilename,
                artifact_id: artifact.artifact_id,
                size_bytes: artifact.size_bytes,
                filter_url: filter_url,
            },
            screenshots: screenshots.getScreenshots(),
        });

        console.log(`[Worker] Job ${job.id}: exported "${suggestedFilename}" (${artifact.size_bytes} bytes)`);
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
        await context.close();
        await browser.close();
        // Clean up temp dir
        await rm(downloadDir, { recursive: true, force: true }).catch(() => {});
    }
}
