import type { ToolContext, ToolOutput } from '../types.js';

/**
 * pm_open_task: Search for a task in the current project and open it.
 * Input: { query: string }
 * Output: { success, task_id, name, path_info, count, results[] }
 *
 * Assumes the page is already on a project page (after pm_open_project).
 * Flow: Click "Úkoly" in project menu → type in #tasks filter → click result.
 */
export async function pmOpenTask(ctx: ToolContext, input: Record<string, unknown>): Promise<ToolOutput> {
	const query = String(input.query ?? '');
	if (!query) {
		return { success: false, error: 'Missing required parameter: query' };
	}

	ctx.log(`Hledám úkol "${query}"...`);

	// Click "Úkoly" in project submenu
	await ctx.page.getByRole('link', { name: 'Úkoly', exact: true }).click();
	await ctx.page.waitForLoadState('networkidle');

	// Type in task search/filter within #tasks section
	const taskFilter = ctx.page.locator('#tasks').getByRole('textbox');
	await taskFilter.fill(query);
	await ctx.page.waitForTimeout(500); // wait for filter to apply

	// Scrape visible task rows from the filtered table
	const results = await ctx.page.evaluate((searchQuery: string) => {
		const tasks: Array<{ task_id: string; name: string; path_info: string }> = [];
		// Look for task links in the tasks table/list
		const links = document.querySelectorAll('#tasks a[href*="tasks%2F"], #tasks a[href*="tasks/"]');
		for (const link of links) {
			const name = link.textContent?.trim() ?? '';
			const href = link.getAttribute('href') ?? '';
			const pathMatch = /path_info=([^&]+)/.exec(href);
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			// Extract task ID from path_info (e.g., projects/xxx/tasks/6)
			const taskIdMatch = /tasks\/(\d+)/.exec(pathInfo);
			const taskId = taskIdMatch ? taskIdMatch[1] : '';

			if (name && taskId && name.toLowerCase().includes(searchQuery.toLowerCase())) {
				// Avoid duplicates
				if (!tasks.some((t) => t.task_id === taskId)) {
					tasks.push({ task_id: taskId, name, path_info: pathInfo });
				}
			}
		}
		return tasks;
	}, query);

	if (results.length === 0) {
		// Try clicking text match directly (popup-style filter)
		const directLink = ctx.page.getByText(query, { exact: false });
		if (await directLink.count() > 0) {
			await directLink.first().click();
			await ctx.page.waitForLoadState('networkidle');

			// Extract info from the task detail page
			const url = ctx.page.url();
			const pathMatch = /path_info=([^&]+)/.exec(url);
			const pathInfo = pathMatch ? decodeURIComponent(pathMatch[1]) : '';
			const taskIdMatch = /tasks\/(\d+)/.exec(pathInfo);

			if (taskIdMatch) {
				const title = await ctx.page.locator('h1').textContent();
				return {
					success: true,
					task_id: taskIdMatch[1],
					name: title?.replace(/^#\d+:\s*/, '').trim() ?? query,
					path_info: pathInfo,
					count: 1,
					results: [{ task_id: taskIdMatch[1], name: title?.trim() ?? query, path_info: pathInfo }],
				};
			}
		}

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

	// Exactly 1 result — click it
	await ctx.page.getByText(results[0].name).first().click();
	await ctx.page.waitForLoadState('networkidle');

	return {
		success: true,
		task_id: results[0].task_id,
		name: results[0].name,
		path_info: results[0].path_info,
		count: 1,
		results,
	};
}
