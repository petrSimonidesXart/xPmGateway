import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { ScreenshotManager } from '../lib/screenshots.js';
import { VideoRecorder } from '../lib/video.js';
import { toolRegistry } from './registry.js';
import type { ToolContext } from './types.js';

const LEGACY_PM_BASE_URL = process.env.LEGACY_PM_BASE_URL || 'https://pm.interni-sit.cz';

/** PM tools (except pm_login) require an active session. */
function needsAutoLogin(toolName: string): boolean {
	return toolName.startsWith('pm_') && toolName !== 'pm_login';
}

/**
 * Run a single tool as a standalone job (own browser session).
 * PM tools automatically login first.
 * Progress is streamed to the adapter via /progress endpoint.
 */
export async function runToolStandalone(job: Job, api: AdapterApi): Promise<void> {
	const tool = toolRegistry[job.tool_name];
	if (!tool) {
		await api.submitResult(job.id, {
			status: 'failed',
			error: `Neznámý nástroj: ${job.tool_name}`,
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

	// Progress log — sent to adapter DB in real time
	const progressLog: Array<{ time: string; message: string }> = [];
	let progressSeq = 0;

	function sendProgress(): void {
		api.updateProgress(job.id, progressLog.map((entry, i) => ({
			id: `log-${i}`,
			status: 'success' as const,
			output: { message: entry.message },
			duration_ms: 0,
		}))).catch(() => {});
	}

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
		log: (message: string) => {
			progressLog.push({ time: new Date().toISOString(), message });
			console.log(`[Worker] Job ${job.id}: ${message}`);
			sendProgress();
		},
	};

	try {
		// Auto-login for PM tools that need an active session
		if (needsAutoLogin(job.tool_name)) {
			ctx.log('Přihlašuji se do PM aplikace...');
			await loginToLegacySystem(
				page,
				LEGACY_PM_BASE_URL,
				job.service_account.username,
				job.service_account.password,
			);
			await screenshots.capture(page, 'auto-login-ok');
			ctx.log('Přihlášení úspěšné');
		}

		ctx.log(`Spouštím nástroj ${job.tool_name}...`);
		const output = await tool(ctx, job.payload);

		ctx.log(output.success ? 'Nástroj dokončen úspěšně' : `Nástroj selhal: ${output.error}`);

		await api.submitResult(job.id, {
			status: output.success ? 'success' : 'failed',
			result: { ...output, _progress: progressLog },
			error: output.success ? undefined : (output.error as string | undefined),
			screenshots: screenshots.getScreenshots(),
		});
	} catch (error) {
		try { await screenshots.capture(page, 'error'); } catch { /* ignore */ }
		const message = error instanceof Error ? error.message : String(error);
		ctx.log(`Chyba: ${message}`);

		await api.submitResult(job.id, {
			status: 'failed',
			error: message,
			result: { _progress: progressLog },
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
