import { chromium } from 'playwright';
import type { Job } from '../index.js';
import type { AdapterApi } from '../lib/api.js';
import { loginToLegacySystem } from '../lib/auth.js';
import { VideoRecorder } from '../lib/video.js';
import { toolRegistry } from './registry.js';
import type { ToolContext } from './types.js';

const FALLBACK_BASE_URL = process.env.FALLBACK_BASE_URL || 'https://pm.interni-sit.cz';

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

	const recorder = new VideoRecorder(job.id);
	await recorder.init();

	const browser = await chromium.launch({ headless: true });
	const context = await browser.newContext({
		...recorder.contextOptions(),
	});
	const page = await context.newPage();
	page.setDefaultTimeout(job.timeout_seconds * 1000);

	const baseUrl = job.service_account.base_url || FALLBACK_BASE_URL;

	// Progress log — sent to adapter DB in real time
	const progressLog: Array<{ time: string; message: string }> = [];

	const ctx: ToolContext = {
		page,
		baseUrl,
		job: {
			id: job.id,
			service_account: job.service_account,
			timeout_seconds: job.timeout_seconds,
		},
		api,
		log: (message: string) => {
			progressLog.push({ time: new Date().toISOString(), message });
			console.log(`[Worker] Job ${job.id}: ${message}`);
			api.updateProgress(job.id, progressLog.map((e, i) => ({
				id: `log-${i}`,
				status: 'success' as const,
				output: { message: e.message },
				duration_ms: 0,
			}))).catch(() => {});
		},
	};

	try {
		// Auto-login for PM tools that need an active session
		if (needsAutoLogin(job.tool_name)) {
			ctx.log('Přihlašuji se do PM aplikace...');
			await loginToLegacySystem(
				page,
				baseUrl,
				job.service_account.username,
				job.service_account.password,
			);
			ctx.log('Přihlášení úspěšné');
		}

		ctx.log(`Spouštím nástroj ${job.tool_name}...`);
		const output = await tool(ctx, job.payload);

		if (output.needs_input) {
			// Tool needs disambiguation — pause job
			ctx.log(`Čekám na vstup: ${output.input_prompt ?? 'vyberte z možností'}`);

			await api.submitAwaitingInput(job.id, {
				options: (output.options ?? []) as Array<Record<string, unknown>>,
				input_prompt: (output.input_prompt as string) ?? 'Vyberte z možností',
				original_payload: job.payload,
				tool_name: job.tool_name,
			});

			console.log(`[Worker] Job ${job.id} (${job.tool_name}) → awaiting_input`);
		} else {
			ctx.log(output.success ? 'Nástroj dokončen úspěšně' : `Nástroj selhal: ${output.error}`);

			await api.submitResult(job.id, {
				status: output.success ? 'success' : 'failed',
				result: { ...output, _progress: progressLog },
				error: output.success ? undefined : (output.error as string | undefined),
			});
		}
	} catch (error) {
		const message = error instanceof Error ? error.message : String(error);
		ctx.log(`Chyba: ${message}`);

		await api.submitResult(job.id, {
			status: 'failed',
			error: message,
			result: { _progress: progressLog },
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
