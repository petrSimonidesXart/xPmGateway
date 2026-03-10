import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://hirola.xart.cz/pmdev/public/index.php';

/**
 * Look up a task by ID using the direct_task form.
 * Goes to the direct_task page, enters the ID, submits,
 * and scrapes task details if found.
 */
export async function handleGetTask(job: Job, api: AdapterApi): Promise<void> {
    const screenshots = new ScreenshotManager(job.id);
    await screenshots.init();

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    page.setDefaultTimeout(job.timeout_seconds * 1000);

    try {
        const { task_id } = job.payload as { task_id: string };

        // Step 1: Login
        await loginToLegacySystem(
            page,
            LEGACY_PM_BASE_URL,
            job.service_account.username,
            job.service_account.password,
        );
        await screenshots.capture(page, 'login-ok');

        // Step 2: Navigate to direct_task form
        await page.goto(`${LEGACY_PM_BASE_URL}?path_info=direct_task`);
        await page.waitForLoadState('networkidle');
        await screenshots.capture(page, 'direct-task-form');

        // Step 3: Fill in task ID and submit
        await page.fill('input[name="task_id"], input[name="id"], input[type="text"]', String(task_id));
        await screenshots.capture(page, 'id-filled');

        await page.click('button[type="submit"], input[type="submit"], button:has-text("Zobrazit"), button:has-text("Najít"), button:has-text("Přejít")');
        await page.waitForLoadState('networkidle');
        await screenshots.capture(page, 'after-submit');

        // Step 4: Check if we got redirected to a task detail or got an error
        const currentUrl = page.url();
        const pageContent = await page.textContent('body') ?? '';

        // Detect error state
        const hasError = await page.locator('.error, .alert-danger, .flash-error, .message-error').count() > 0;
        const isStillOnForm = currentUrl.includes('direct_task');

        if (hasError || isStillOnForm) {
            // Task not found — extract error message
            const errorElement = await page.locator('.error, .alert-danger, .flash-error, .flash-message, .message-error').first();
            const errorText = await errorElement.textContent().catch(() => null);

            await screenshots.capture(page, 'error');

            await api.submitResult(job.id, {
                status: 'success',
                result: {
                    found: false,
                    task_id: task_id,
                    error: errorText?.trim() || 'Task not found',
                },
                screenshots: screenshots.getScreenshots(),
            });

            console.log(`[Worker] Job ${job.id}: task ${task_id} not found`);
            return;
        }

        // Step 5: Task found — scrape details from the task detail page
        await screenshots.capture(page, 'task-detail');

        const taskData = await page.evaluate(() => {
            const getText = (selector: string): string | null => {
                const el = document.querySelector(selector);
                return el?.textContent?.trim() || null;
            };

            const getFieldValue = (label: string): string | null => {
                // Try to find a field by its label text
                const labels = document.querySelectorAll('label, .field-label, dt, th');
                for (const lbl of labels) {
                    if (lbl.textContent?.trim().toLowerCase().includes(label.toLowerCase())) {
                        // Check next sibling, adjacent dd/td, or associated input
                        const next = lbl.nextElementSibling;
                        if (next) return next.textContent?.trim() || null;
                    }
                }
                return null;
            };

            return {
                title: getText('h1, h2, .task-title, .task-name') || getText('.detail-title'),
                status: getFieldValue('stav') || getFieldValue('status'),
                assignee: getFieldValue('řešitel') || getFieldValue('assignee') || getFieldValue('přiřazeno'),
                project: getFieldValue('projekt') || getFieldValue('project'),
                due_date: getFieldValue('termín') || getFieldValue('due') || getFieldValue('deadline'),
                priority: getFieldValue('priorita') || getFieldValue('priority'),
                created: getFieldValue('vytvořeno') || getFieldValue('created'),
                description: getText('.task-description, .description, .detail-description'),
                url: window.location.href,
            };
        });

        await api.submitResult(job.id, {
            status: 'success',
            result: {
                found: true,
                task_id: task_id,
                ...taskData,
            },
            screenshots: screenshots.getScreenshots(),
        });

        console.log(`[Worker] Job ${job.id}: task ${task_id} found — "${taskData.title}"`);
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
    }
}
