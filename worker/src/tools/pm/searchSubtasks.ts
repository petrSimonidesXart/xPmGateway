import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_search_subtasks: Search in subtasks of the currently opened task.
 * Input: { query: string }
 * Output: { success, results[], count }
 */
export async function pmSearchSubtasks(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');

	const results = await ctx.page.evaluate((searchQuery: string) => {
		const subtasks: Array<{ name: string; path_info: string; status: string; assignee: string }> = [];
		const subtaskEls = document.querySelectorAll(
			'.subtask, .subtask-item, [class*="subtask"], '
			+ 'tr[class*="subtask"], li[class*="subtask"]',
		);

		for (const el of subtaskEls) {
			const linkEl = el.querySelector('a[href*="subtask"]') ?? el.querySelector('a');
			const name = linkEl?.textContent?.trim() ?? el.querySelector('.name, .title')?.textContent?.trim() ?? '';
			const href = linkEl?.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			const status = el.querySelector('.status, [class*="status"]')?.textContent?.trim() ?? '';
			const assignee = el.querySelector('.assignee, [class*="assignee"]')?.textContent?.trim() ?? '';

			if (!name) continue;
			if (searchQuery && !name.toLowerCase().includes(searchQuery.toLowerCase())) continue;

			subtasks.push({ name, path_info: pathInfo, status, assignee });
		}

		return subtasks;
	}, query);

	return {
		success: true,
		results,
		count: results.length,
	};
}
