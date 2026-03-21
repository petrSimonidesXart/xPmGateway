import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_close_task: Close the currently opened task.
 * Input: — (operates on current page)
 * Output: { success }
 */
export async function pmCloseTask(ctx: ToolContext, _input: Record<string, unknown>): Promise<ToolOutput> {
	const closeBtn = ctx.page.locator(
		'a:has-text("Zavřít"), button:has-text("Zavřít"), '
		+ 'a:has-text("Close"), button:has-text("Close"), '
		+ 'a:has-text("Dokončit"), button:has-text("Dokončit"), '
		+ '.close-task, .complete-task, '
		+ '[data-action="close"], [data-action="complete"]',
	);

	if (await closeBtn.count() === 0) {
		return { success: false, error: 'Close/complete button not found on page' };
	}

	await closeBtn.first().click();
	await ctx.page.waitForLoadState('networkidle');

	return { success: true };
}
