import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_close_subtask: Close a subtask by navigating to it and clicking close.
 * Input: { path_info: string }
 * Output: { success }
 */
export async function pmCloseSubtask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const pathInfo = String(input.path_info ?? '');
	if (!pathInfo) {
		return { success: false, error: 'Missing required parameter: path_info' };
	}

	await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(pathInfo)}`);
	await ctx.page.waitForLoadState('networkidle');

	const closeBtn = ctx.page.locator(
		'a:has-text("Zavřít"), button:has-text("Zavřít"), '
		+ 'a:has-text("Close"), button:has-text("Close"), '
		+ 'a:has-text("Dokončit"), button:has-text("Dokončit"), '
		+ '.close-subtask, [data-action="close"]',
	);

	if (await closeBtn.count() === 0) {
		return { success: false, error: 'Close button not found on subtask page' };
	}

	await closeBtn.first().click();
	await ctx.page.waitForLoadState('networkidle');

	return { success: true };
}
