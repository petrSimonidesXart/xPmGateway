import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_close_task: Close the currently opened task.
 * Input: { keep_subtasks?: boolean }
 * Output: { success }
 *
 * Uses "Dokončit" or "Dokončit (podúkoly neuzavírat)" link on task detail page.
 */
export async function pmCloseTask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const keepSubtasks = input.keep_subtasks === true;

	const linkName = keepSubtasks
		? 'Dokončit (podúkoly neuzavírat)'
		: 'Dokončit';

	const closeLink = ctx.page.getByRole('link', { name: linkName, exact: !keepSubtasks });

	if (await closeLink.count() === 0) {
		return { success: false, error: `Link "${linkName}" not found — task may already be completed` };
	}

	await closeLink.click();
	await ctx.page.waitForLoadState('networkidle');

	return { success: true };
}
