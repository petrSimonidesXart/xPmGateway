import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_open_task: Search for a task in the current project and open it.
 * Input: { query: string }
 * Output: { success, task_id, name, path_info, status, count, results[] }
 *
 * Assumes the page is already on a project page (after pm_open_project).
 * If exactly 1 match → opens task and returns its details.
 * If 0 or >1 matches → returns success=false with list for disambiguation.
 */
export async function pmOpenTask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');
	if (!query) {
		return { success: false, error: 'Missing required parameter: query' };
	}

	// Look for task search/filter on the current project page
	const searchInput = ctx.page.locator(
		'input[name*="search"], input[name*="filter"], input[type="search"], '
		+ 'input[placeholder*="Hledat"], input[placeholder*="hled"], '
		+ 'input[placeholder*="úkol"]',
	);

	if (await searchInput.count() > 0) {
		await searchInput.first().fill(query);
		const searchBtn = ctx.page.locator(
			'button:has-text("Hledat"), button:has-text("Filtrovat"), '
			+ 'button[type="submit"]',
		);
		if (await searchBtn.count() > 0) {
			await searchBtn.first().click();
		} else {
			await searchInput.first().press('Enter');
		}
		await ctx.page.waitForLoadState('networkidle');
	}

	// Scrape task list
	const results = await ctx.page.evaluate((searchQuery: string) => {
		const tasks: Array<{ task_id: string; name: string; path_info: string; status: string }> = [];
		// Look for task links - various PM app patterns
		const links = document.querySelectorAll('a[href*="tasks"]');
		for (const link of links) {
			const name = link.textContent?.trim() ?? '';
			const href = link.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';

			// Extract task ID from path_info (e.g., projects/xxx/tasks/123)
			const taskIdMatch = /tasks\/(\d+)/.exec(pathInfo);
			const taskId = taskIdMatch ? taskIdMatch[1] : '';

			if (name && taskId) {
				if (name.toLowerCase().includes(searchQuery.toLowerCase())) {
					// Try to find status from adjacent element
					const row = link.closest('tr, .task-item, .task-row, li');
					const statusEl = row?.querySelector('.task-status, .status, [class*="status"]');
					const status = statusEl?.textContent?.trim() ?? '';

					tasks.push({ task_id: taskId, name, path_info: pathInfo, status });
				}
			}
		}
		return tasks;
	}, query);

	if (results.length === 0) {
		return {
			success: false,
			error: `Úkol "${query}" nenalezen v aktuálním projektu`,
			count: 0,
			results: [],
		};
	}

	if (results.length > 1) {
		return {
			success: false,
			error: `Nalezeno ${results.length} úkolů pro "${query}". Upřesněte název.`,
			count: results.length,
			results,
		};
	}

	// Exactly 1 result — navigate to it
	const task = results[0];
	await ctx.page.goto(`${ctx.baseUrl}?path_info=${encodeURIComponent(task.path_info)}`);
	await ctx.page.waitForLoadState('networkidle');

	return {
		success: true,
		task_id: task.task_id,
		name: task.name,
		path_info: task.path_info,
		status: task.status,
		count: 1,
		results,
	};
}
