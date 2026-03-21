import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { ScreenshotManager } from '../lib/screenshots.js';
import { VideoRecorder } from '../lib/video.js';
import { toolRegistry } from './registry.js';
import type { ToolContext } from './types.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://pm.interni-sit.cz';

/**
 * Run a single tool as a standalone job (own browser session).
 * This wraps the dual-mode tool function with browser lifecycle management.
 */
export async function runToolStandalone(job: Job, api: AdapterApi): Promise<void> {
	const tool = toolRegistry[job.tool_name];
	if (!tool) {
		await api.submitResult(job.id, {
			status: 'failed',
			error: `Unknown tool: ${job.tool_name}`,
		});
		return;
	}

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

	const ctx: ToolContext = {
		page,
		baseUrl: LEGACY_PM_BASE_URL,
		job: {
			id: job.id,
			service_account: job.service_account,
			timeout_seconds: job.timeout_seconds,
		},
		api,
		screenshots,
	};

	try {
		const output = await tool(ctx, job.payload);

		await api.submitResult(job.id, {
			status: output.success ? 'success' : 'failed',
			result: output,
			error: output.success ? undefined : (output.error as string | undefined),
			screenshots: screenshots.getScreenshots(),
		});

		console.log(`[Worker] Job ${job.id} (${job.tool_name}) completed — success=${output.success}`);
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
