import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';
import { VideoRecorder } from '../lib/video.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://pm.interni-sit.cz';

export async function handleCreateTask(job: Job, api: AdapterApi): Promise<void> {
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
        // Step 1: Login
        await loginToLegacySystem(
            page,
            LEGACY_PM_BASE_URL,
            job.service_account.username,
            job.service_account.password,
        );
        await screenshots.capture(page, 'login-ok');

        // Step 2: Navigate to task creation
        const { title, project, assignee, due_date, estimate_hours } = job.payload as {
            title: string;
            project: string;
            assignee?: string;
            due_date?: string;
            estimate_hours?: number;
        };

        await page.goto(`${LEGACY_PM_BASE_URL}/projects`);
        await page.waitForLoadState('networkidle');

        // Find and click the project
        await page.click(`a:has-text("${project}"), [data-project="${project}"]`);
        await page.waitForLoadState('networkidle');
        await screenshots.capture(page, 'project-page');

        // Click "New Task"
        await page.click('a:has-text("New Task"), a:has-text("Nový úkol"), button:has-text("New Task"), .new-task-btn');
        await page.waitForLoadState('networkidle');

        // Step 3: Fill the form
        await page.fill('input[name="title"], input[name="name"], #task-title', title);

        if (assignee) {
            await page.fill('input[name="assignee"], select[name="assignee"], #assignee', assignee);
        }
        if (due_date) {
            await page.fill('input[name="due_date"], input[type="date"], #due-date', due_date);
        }
        if (estimate_hours !== undefined) {
            await page.fill('input[name="estimate_hours"], input[name="estimate"], #estimate', String(estimate_hours));
        }

        await screenshots.capture(page, 'form-filled');

        // Step 4: Submit
        await page.click('button[type="submit"], input[type="submit"], .submit-btn');
        await page.waitForLoadState('networkidle');
        await screenshots.capture(page, 'submitted');

        // Step 5: Extract task ID
        const taskIdElement = await page.$('.task-id, [data-task-id], .flash-message');
        const taskIdText = taskIdElement ? await taskIdElement.textContent() : null;
        const taskIdMatch = taskIdText?.match(/\d+/);
        const taskId = taskIdMatch ? taskIdMatch[0] : null;

        await screenshots.capture(page, 'result');

        await api.submitResult(job.id, {
            status: 'success',
            result: { task_id: taskId, message: 'Task created successfully' },
            screenshots: screenshots.getScreenshots(),
        });

        console.log(`[Worker] Job ${job.id} completed. Task ID: ${taskId}`);
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
