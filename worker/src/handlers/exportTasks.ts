import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';
import { VideoRecorder } from '../lib/video.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://pm.interni-sit.cz';

/**
 * Example handler that produces an artifact.
 * Scrapes tasks from legacy PM and returns a CSV file.
 */
export async function handleExportTasks(job: Job, api: AdapterApi): Promise<void> {
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
        const { project, status, format } = job.payload as {
            project: string;
            status?: string;
            format?: 'csv' | 'json';
        };
        const outputFormat = format ?? 'csv';

        // Step 1: Login
        await loginToLegacySystem(
            page,
            LEGACY_PM_BASE_URL,
            job.service_account.username,
            job.service_account.password,
        );
        await screenshots.capture(page, 'login-ok');

        // Step 2: Navigate to project tasks
        await page.goto(`${LEGACY_PM_BASE_URL}/projects`);
        await page.waitForLoadState('networkidle');
        await page.click(`a:has-text("${project}"), [data-project="${project}"]`);
        await page.waitForLoadState('networkidle');
        await screenshots.capture(page, 'project-page');

        // Step 3: Scrape task data from the page
        const tasks = await page.evaluate((filterStatus?: string) => {
            const rows = document.querySelectorAll('table.tasks tr, .task-list .task-item');
            const result: Array<Record<string, string>> = [];

            rows.forEach((row) => {
                const title = row.querySelector('.task-title, td:nth-child(1)')?.textContent?.trim() ?? '';
                const assignee = row.querySelector('.task-assignee, td:nth-child(2)')?.textContent?.trim() ?? '';
                const taskStatus = row.querySelector('.task-status, td:nth-child(3)')?.textContent?.trim() ?? '';
                const dueDate = row.querySelector('.task-due-date, td:nth-child(4)')?.textContent?.trim() ?? '';

                if (!title) return;
                if (filterStatus && taskStatus.toLowerCase() !== filterStatus.toLowerCase()) return;

                result.push({ title, assignee, status: taskStatus, due_date: dueDate });
            });

            return result;
        }, status);

        await screenshots.capture(page, 'tasks-scraped');

        // Step 4: Generate output file
        let content: string;
        let mimeType: string;
        let filename: string;

        if (outputFormat === 'json') {
            content = JSON.stringify(tasks, null, 2);
            mimeType = 'application/json';
            filename = `export-${project}.json`;
        } else {
            // CSV format
            const headers = ['title', 'assignee', 'status', 'due_date'];
            const csvRows = [
                headers.join(','),
                ...tasks.map((task) =>
                    headers
                        .map((h) => `"${(task[h] ?? '').replace(/"/g, '""')}"`)
                        .join(','),
                ),
            ];
            content = csvRows.join('\n');
            mimeType = 'text/csv';
            filename = `export-${project}.csv`;
        }

        // Step 5: Upload artifact
        const artifact = await api.uploadArtifactContent(
            job.id,
            content,
            filename,
            mimeType,
            { project, status: status ?? 'all', rows: tasks.length },
        );

        // Step 6: Submit result
        await api.submitResult(job.id, {
            status: 'success',
            result: {
                rows_exported: tasks.length,
                format: outputFormat,
                artifact_id: artifact.artifact_id,
                filename: artifact.filename,
            },
            screenshots: screenshots.getScreenshots(),
        });

        console.log(`[Worker] Job ${job.id} completed. Exported ${tasks.length} tasks.`);
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
